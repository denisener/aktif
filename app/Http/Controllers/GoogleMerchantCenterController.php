<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;

class GoogleMerchantCenterController extends Controller
{
    private $merchantId;
    
    public function __construct()
    {
        $this->merchantId = config('google.merchant_id');
    }
    /**
     * Display the product feed generation page
     */
    public function productFeedGenerate(Request $request)
    {
        $seller_type = 'all';
        $product_types = ['All Products', 'Inhouse Products', 'Seller Products'];
        $categories = Category::where('parent_id', 0)->with('childrenCategories')->get();
        
        return view('backend.gmc.index', compact('seller_type', 'product_types', 'categories'));
    }


    /**
     * Export selected products to XML (GET request)
     */
    public function generateXMLFeed(Request $request)
    {
        $productIds = $request->product_ids ? explode(',', $request->product_ids) : [];
        
        if (empty($productIds)) {
            Log::error('No products selected for XML export');
            return response()->json(['error' => 'No products selected'], 400);
        }
        
        $products = Product::with(['brand', 'main_category'])
            ->whereIn('id', $productIds)
            ->where('published', 1)
            ->where('approved', 1)
            ->where('draft', 0)
            ->get();
        
        if ($products->isEmpty()) {
            Log::error('No valid products found for XML export');
            return response()->json(['error' => 'No valid products found'], 404);
        }
        
        return $this->generateXML($products);
    }

    /**
     * Export selected products to CSV (GET request)
     */
    public function generateCSVFeed(Request $request)
    {
        //Log::info('generateCSVFeed called', ['product_ids' => $request->product_ids]);
        
        $productIds = $request->product_ids ? explode(',', $request->product_ids) : [];
        
        if (empty($productIds)) {
            //Log::error('No products selected for CSV export');
            return response()->json(['error' => 'No products selected'], 400);
        }
        
        $products = Product::with(['brand', 'main_category'])
            ->whereIn('id', $productIds)
            ->where('published', 1)
            ->where('approved', 1)
            ->where('draft', 0)
            ->get();
        
        //Log::info('Products found for CSV export', ['count' => $products->count()]);
        
        if ($products->isEmpty()) {
           // Log::error('No valid products found for CSV export');
            return response()->json(['error' => 'No valid products found'], 404);
        }
        
        return $this->generateCSV($products);
    }


    /**
     * Generate XML feed
     */
    private function generateXML($products)
    {
        try {
            //Log::info('Starting XML generation', ['product_count' => $products->count()]);
            
            $currency = get_system_currency();
            $siteUrl = url('/');
            $siteName = get_setting('site_name');
            
            // Start with proper XML header
            $xmlString = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xmlString .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
            $xmlString .= '<channel>' . "\n";
            
            $xmlString .= '<title>' . $this->xmlEscape($siteName . ' - Product Feed') . '</title>' . "\n";
            $xmlString .= '<link>' . $siteUrl . '</link>' . "\n";
            $xmlString .= '<description>' . $this->xmlEscape('Product feed for Google Merchant Center') . '</description>' . "\n";
            
            $processedCount = 0;
            
            foreach ($products as $product) {
                try {
                    $xmlString .= '<item>' . "\n";
                    
                    $xmlString .= '<g:id>' . $product->id . '</g:id>' . "\n";
                    $xmlString .= '<title>' . $this->xmlEscape($product->getTranslation('name')) . '</title>' . "\n";
                    
                    $description = strip_tags($product->getTranslation('description'));
                    $description = $this->cleanString($description);
                    $description = substr($description, 0, 5000);
                    $xmlString .= '<description>' . $this->xmlEscape($description) . '</description>' . "\n";
                    
                    $xmlString .= '<g:link>' . $this->xmlEscape(route('product', $product->slug)) . '</g:link>' . "\n";
                    $xmlString .= '<g:image_link>' . $this->xmlEscape(uploaded_asset($product->thumbnail_img)) . '</g:image_link>' . "\n";
                    
                    $price = $product->unit_price;
                    if ($product->discount > 0 && $product->discount_type == 'percent') {
                        $price = $product->unit_price - ($product->unit_price * $product->discount / 100);
                    } elseif ($product->discount > 0 && $product->discount_type == 'amount') {
                        $price = $product->unit_price - $product->discount;
                    }
                    
                    $priceFormatted = number_format($price, 2, '.', '') . ' ' . $currency->symbol;
                    $xmlString .= '<g:price>' . $priceFormatted . '</g:price>' . "\n";
                    
                    $availability = $product->stock_quantity > 0 ? 'in stock' : 'out of stock';
                    $xmlString .= '<g:availability>' . $availability . '</g:availability>' . "\n";
                    
                    $brand = $product->brand->name ?? 'No Brand';
                    $xmlString .= '<g:brand>' . $this->xmlEscape($brand) . '</g:brand>' . "\n";
                    
                    $xmlString .= '<g:condition>new</g:condition>' . "\n";
                    
                    $mpn = $product->sku ?? (string)$product->id;
                    $xmlString .= '<g:mpn>' . $this->xmlEscape($mpn) . '</g:mpn>' . "\n";
                    
                    $productType = $product->main_category->name ?? 'Uncategorized';
                    $xmlString .= '<g:product_type>' . $this->xmlEscape($productType) . '</g:product_type>' . "\n";
                    
                    $xmlString .= '</item>' . "\n";
                    
                    $processedCount++;
                    
                } catch (\Exception $e) {
                    Log::error('Error processing product for XML', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            $xmlString .= '</channel>' . "\n";
            $xmlString .= '</rss>';
            
            $xmlString = $this->cleanInvalidXmlChars($xmlString);
            
            //Log::info('XML generation completed', ['processed_count' => $processedCount]);
            
            $response = response($xmlString, 200);
            $response->header('Content-Type', 'application/xml; charset=UTF-8');
            $response->header('Content-Disposition', 'attachment; filename="google_feed_' . date('Y-m-d_H-i-s') . '.xml"');
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('XML generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'XML generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate CSV feed
     */
    private function generateCSV($products)
    {
        try {
            //Log::info('Starting CSV generation', ['product_count' => $products->count()]);
            
            $currency = get_system_currency();
            $filename = 'google_feed_' . date('Y-m-d_H-i-s') . '.csv';
            $handle = fopen('php://temp', 'w+');
            
            // Add BOM for UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($handle, ['ID', 'Title', 'Description', 'Link', 'Image Link', 'Price', 'Availability', 'Brand', 'Condition', 'MPN', 'Product Type']);
            
            $processedCount = 0;
            
            foreach ($products as $product) {
                try {
                    $price = $product->unit_price;
                    if ($product->discount > 0 && $product->discount_type == 'percent') {
                        $price = $product->unit_price - ($product->unit_price * $product->discount / 100);
                    } elseif ($product->discount > 0 && $product->discount_type == 'amount') {
                        $price = $product->unit_price - $product->discount;
                    }
                    
                    fputcsv($handle, [
                        $product->id,
                        $product->getTranslation('name'),
                        strip_tags($product->getTranslation('description')),
                        route('product', $product->slug),
                        uploaded_asset($product->thumbnail_img),
                        number_format($price, 2, '.', '') . ' ' . $currency->symbol,
                        $product->stock_quantity > 0 ? 'in stock' : 'out of stock',
                        $product->brand->name ?? 'No Brand',
                        'new',
                        $product->sku ?? $product->id,
                        $product->main_category->name ?? ''
                    ]);
                    
                    $processedCount++;
                    
                } catch (\Exception $e) {
                    Log::error('Error processing product for CSV', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);
            
            // Log::info('CSV generation completed', ['processed_count' => $processedCount]);
            
            $response = response($csvContent, 200);
            $response->header('Content-Type', 'text/csv; charset=UTF-8');
            $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('CSV generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'CSV generation failed: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Export selected products (POST)
     */
    public function exportProducts(Request $request)
    {
        try {
            $request->validate(['format' => 'required|in:xml,csv,txt']);

            $products = Product::with(['brand', 'main_category'])
                ->where('published', 1)
                ->where('approved', 1)
                ->where('draft', 0)
                ->limit(5000)
                ->get();

            if($request->product_ids) {
                $products = $products->whereIn('id', $request->product_ids);
            }

            //Log::info('Products found for export all', ['count' => $products->count()]);

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products found.'
                ], 404);
            }

            switch ($request->format) {
                case 'xml':
                    return $this->generateXML($products);
                case 'csv':
                    return $this->generateCSV($products);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid format selected.'
                    ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('exportAllProducts failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Export all products (POST)
     */

    // ========== HELPER METHODS ==========

    private function xmlEscape($string)
    {
        if ($string === null || $string === '') {
            return '';
        }
        
        $string = (string) $string;
        $string = $this->cleanInvalidXmlChars($string);
        
        $string = str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $string
        );
        
        $string = preg_replace('/&amp;(?!amp;|lt;|gt;|quot;|apos;)/', '&', $string);
        $string = str_replace('&', '&amp;', $string);
        
        return $string;
    }

    private function cleanString($string)
    {
        if ($string === null || $string === '') {
            return '';
        }
        
        $string = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        
        return trim($string);
    }

    private function cleanInvalidXmlChars($string)
    {
        if ($string === null || $string === '') {
            return '';
        }
        
        $pattern = '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u';
        $string = preg_replace($pattern, ' ', $string);
        
        return $string;
    }

    public function get_gmc_products(Request $request)
    {
        //Log::info('Filter Products Request: ', $request->all());
        $col_name = null;
        $query = null;
        $sort_search = null;
        
        $products = Product::where('auction_product', 0)
            ->where('wholesale_product', 0)
            ->where('draft', 0)
            ->where('gmc', 1)
            ->where('published', 1)
            ->where('approved', 1);
        
        if ($request->seller_type == 'admin') {
            $products = $products->where('added_by', 'admin');
        } elseif ($request->seller_type == 'seller') {
            $products = $products->where('added_by', 'seller');
            if ($request->user_id != null) {
                $products = $products->where('user_id', $request->user_id);
            }
        }

        if ($request->search != null) {
            $sort_search = $request->search;
            $products = $products->where(function($query) use ($sort_search) {
                $query->where('name', 'like', '%' . $sort_search . '%')
                    ->orWhereHas('stocks', function ($q) use ($sort_search) {
                        $q->where('sku', 'like', '%' . $sort_search . '%');
                    });
            });
        }
        
        if ($request->type != null) {
            $var = explode(",", $request->type);
            $col_name = $var[0];
            $query = $var[1];
            $products = $products->orderBy($col_name, $query);
        }

        $filters = $request->selected_filter ?? [];
        if (!empty($filters)) {
            if (in_array('low-stock', $filters)) {
                $products->where(function ($query) {
                    $query->whereRaw("
                        (
                            SELECT CASE
                                WHEN products.variant_product = 1 
                                    THEN (SELECT SUM(qty) FROM product_stocks WHERE product_stocks.product_id = products.id)
                                ELSE 
                                    (SELECT qty FROM product_stocks WHERE product_stocks.product_id = products.id LIMIT 1)
                            END
                        ) <= products.low_stock_quantity
                    ");
                });
            }
            if (in_array('all-discount', $filters)) {
                $products->where('discount', '>', 0);
            }
            if (in_array('all-publish', $filters)) {
                $products->where('published', 1);
            }
            if (in_array('refundable', $filters)) {
                $products->where('refundable', 1);
            }
        }
        
        $products = $products->orderBy('created_at', 'desc')->paginate(15);
        
        $type = $request->seller_type;
        $view = view('backend.gmc.products_table',
            compact('products', 'type', 'col_name', 'query', 'sort_search')
        )->render();

        return response()->json(['html' => $view]);
    }

    public function productUpdateToGMC(Request $request)
    {
        $productIds = $request->id ?? $request->ids;
        if (!$productIds && $request->gmc) {
            return response()->json([
                'success' => false, 
                'message' => translate('No products selected')
            ], 400);
        }
        
        if (is_string($productIds)) {
            $productIds = explode(',', $productIds);
        }
        if (!isset ($request->gmc)) {
            Product::where('gmc', 1)->update(['gmc' => 0]);
            if (!empty($productIds)) {
               Product::whereIn('id', $productIds)->update(['gmc' => 1]);
            }
        }else{
            Product::whereIn('id', $productIds)->update(['gmc' => $request->gmc]);
        }

        return response()->json([
            'success' => true,
            'message' => ('GMC Products Updated successfully')
        ]);
    }

    public function gmc_products_search(Request $request)
    {
        $gmc = 1;
        $products = Product::where('auction_product', 0)->where('wholesale_product', 0)->where('draft', 0)->where('published', 1)->where('approved', 1);
        if($request->search_key != null){
            $products = $products->where('name', 'like', '%' . $request->search_key . '%');
        }
        if($request->category != null){
            $category = Category::with('childrenCategories')->find($request->category);
            $products = $category->products();
        }
        $products = $products->get();
        $single_select = $request->single_select ?? 0;
        return view('partials.product.products_search', compact('products', 'single_select', 'gmc'));
    }


    public function xmlFeed()
    {
        $products = Product::where('auction_product', 0)->where('wholesale_product', 0)->where('draft', 0)->where('gmc', 1)->where('published', 1)->where('approved', 1)->get();  
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>' . config('app.name') . ' Products</title>';
        $xml .= '<link>' . url('/') . '</link>';
        $xml .= '<description>Product Feed for Google Merchant Center</description>';
        
        foreach($products as $product) {
            $xml .= '<item>';
            $xml .= '<g:id>' . $product->id . '</g:id>';
            $xml .= '<title>' . $this->clean($product->name) . '</title>';
            $xml .= '<description>' . $this->clean(substr($product->description, 0, 5000)) . '</description>';
            $xml .= '<g:link>' . url('/product/' . $product->slug) . '</g:link>';
            $xml .= '<g:image_link>' . asset($product->thumbnail_img) . '</g:image_link>';
            $xml .= '<g:price>' . $product->unit_price . ' BDT</g:price>';
            $xml .= '<g:availability>' . ($product->stock > 0 ? 'in stock' : 'out of stock') . '</g:availability>';
            $xml .= '<g:brand>' . ($product->brand->name ?? 'Generic') . '</g:brand>';
            $xml .= '<g:condition>new</g:condition>';
            $xml .= '<g:mpn>' . $product->id . '</g:mpn>';
            $xml .= '<g:product_type>' . $this->clean($product->category->name ?? 'Uncategorized') . '</g:product_type>';
            $xml .= '</item>';
        }
        
        $xml .= '</channel></rss>';
        
        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
    
   
    public function manualPushToGoogle(Request $request)
    {
        try {
            $products = Product::where('auction_product', 0)
                ->where('wholesale_product', 0)
                ->where('draft', 0)
                ->where('gmc', 1)
                ->where('published', 1)
                ->where('approved', 1)
                ->get();

            if($request->id) {
                $products = $products->whereIn('id', $request->id);
            }
            $successCount = 0;
            $failedCount = 0;
            
            foreach($products as $product) {
                $response = $this->sendToGoogleAPI($product);
                
                if($response && $response->successful()) {
                    $successCount++;
                } else {
                    $failedCount++;
                    Log::error('Google Merchant push failed for product: ' . $product->id);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Sync complete! Success: {$successCount}, Failed: {$failedCount}"
            ]);
            
        } catch(\Exception $e) {
            Log::error('Google Merchant sync error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function pushSingleProduct($productId)
    {
        try {
            $product = Product::findOrFail($productId);
            $response = $this->sendToGoogleAPI($product);
            
            // Log::info('Google API Response', [
            //     'success' => $response && $response->successful(),
            //     'status' => $response ? $response->status() : 'null',
            //     'body' => $response ? $response->body() : 'no response'
            // ]);
            
            if($response && $response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product pushed to Google successfully!'
                ]);
            }
            
            $errorMessage = $response ? $response->body() : 'No response from Google API';
            return response()->json([
                'success' => false,
                'message' => 'Push failed: ' . $errorMessage
            ], 400);
            
        } catch(\Exception $e) {
            \Log::error('Push error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function sendToGoogleAPI($product)
    {
        $accessToken = $this->getAccessToken();
        
        if(!$accessToken) {
            Log::error('Failed to get access token');
            return null;
        }
        
        $googleApiUrl = "https://www.googleapis.com/content/v2.1/{$this->merchantId}/products";
        
        $data = [
            'offerId' => (string) $product->id,
            'title' => $this->clean($product->name),
            'description' => $this->clean(substr($product->description, 0, 5000)),
            'link' => url('/product/' . $product->slug),
            'imageLink' => asset($product->thumbnail_img),
            'contentLanguage' => 'en',
            'targetCountry' => 'US',
            'channel' => 'online',
            'availability' => ($product->current_stock > 0) ? 'in stock' : 'out of stock',
            'price' => [
                'value' => (string) $product->unit_price,
                'currency' => get_system_currency()->code
            ],
            'brand' => $this->clean($product->brand->name ?? 'Generic'),
            'condition' => 'new',
            'mpn' => (string) $product->id,
            'productTypes' => [$this->clean($product->main_category->name ?? 'Uncategorized')],
            'identifierExists' => true 
        ];
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->post($googleApiUrl, $data);
            
            Log::info('Google Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return $response;
            
        } catch(\Exception $e) {
            Log::error('HTTP Error: ' . $e->getMessage());
            return null;
        }
    }
    
    private function getAccessToken()
    {
        try {
            $client = new GoogleClient();
            $jsonPath = storage_path(config('google.account_json_path'));
            
            if(!file_exists($jsonPath)) {
                Log::error('Google Service Account JSON not found at: ' . $jsonPath);
                return null;
            }
            
            $client->setAuthConfig($jsonPath);
            $client->addScope('https://www.googleapis.com/auth/content');
            $token = $client->fetchAccessTokenWithAssertion();
            
            if(isset($token['access_token'])) {
               // Log::info('Access token obtained successfully');
                return $token['access_token'];
            }
            
            Log::error('Failed to get access token: ' . json_encode($token));
            return null;
            
        } catch(\Exception $e) {
            Log::error('Google Token Error: ' . $e->getMessage());
            return null;
        }
    }
    
    private function clean($string)
    {
        return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
    }

    public function gmc_configuration(Request $request)
    {
        return view('backend.setup_configurations.google_configuration.gmc_configuration');
    }
}