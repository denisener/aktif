<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookCatalogueController extends Controller
{
    private $catalogueId;
    private $accessToken;
    
    public function __construct()
    {
        $this->catalogueId = env('FACEBOOK_CATALOGUE_ID');
        $this->accessToken = env('FACEBOOK_ACCESS_TOKEN');
    }

    public function productFeedGenerate(Request $request)
    {
        $seller_type = 'all';
        $product_types = ['All Products', 'Inhouse Products', 'Seller Products'];
        $categories = Category::where('parent_id', 0)->with('childrenCategories')->get();
        
        return view('backend.catalogue.index', compact('seller_type', 'product_types', 'categories'));
    }

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

    public function generateCSVFeed(Request $request)
    {
        
        $productIds = $request->product_ids ? explode(',', $request->product_ids) : [];
        
        if (empty($productIds)) {
            Log::error('No products selected for CSV export');
            return response()->json(['error' => 'No products selected'], 400);
        }
        
        $products = Product::with(['brand', 'main_category'])
            ->whereIn('id', $productIds)
            ->where('published', 1)
            ->where('approved', 1)
            ->where('draft', 0)
            ->get();
        
        
        if ($products->isEmpty()) {
            Log::error('No valid products found for CSV export');
            return response()->json(['error' => 'No valid products found'], 404);
        }
        
        return $this->generateCSV($products);
    }


    private function generateXML($products)
    {
        try {
            
            $currency = get_system_currency();
            $siteUrl = url('/');
            $siteName = get_setting('site_name');
            
            $xmlString = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xmlString .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
            $xmlString .= '<channel>' . "\n";
            
            $xmlString .= '<title>' . $this->xmlEscape($siteName . ' - Facebook Catalog') . '</title>' . "\n";
            $xmlString .= '<link>' . $siteUrl . '</link>' . "\n";
            $xmlString .= '<description>' . $this->xmlEscape('Product feed for Meta Facebook Catalog') . '</description>' . "\n";
            
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
                    $xmlString .= '<g:image_link>' . $this->xmlEscape(asset($product)) . '</g:image_link>' . "\n";
                    
                    $price = $product->unit_price;
                    if ($product->discount > 0 && $product->discount_type == 'percent') {
                        $price = $product->unit_price - ($product->unit_price * $product->discount / 100);
                    } elseif ($product->discount > 0 && $product->discount_type == 'amount') {
                        $price = $product->unit_price - $product->discount;
                    }
                    
                    $priceFormatted = number_format($price, 2, '.', '') . ' ' . $currency->code;
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
                    Log::error('Error processing product for Facebook XML', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            $xmlString .= '</channel>' . "\n";
            $xmlString .= '</rss>';
            
            $xmlString = $this->cleanInvalidXmlChars($xmlString);
            
            $response = response($xmlString, 200);
            $response->header('Content-Type', 'application/xml; charset=UTF-8');
            $response->header('Content-Disposition', 'attachment; filename="facebook_catalogue_' . date('Y-m-d_H-i-s') . '.xml"');
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Facebook XML generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'XML generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateCSV($products)
    {
        try {
            
            $filename = 'facebook_catalogue_' . date('Y-m-d_H-i-s') . '.csv';
            $handle = fopen('php://temp', 'w+');
            
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            $headers = [
                'id', 'title', 'description', 'availability', 'condition', 'price',
                'link', 'image_link', 'brand', 'google_product_category', 'fb_product_category',
                'quantity_to_sell_on_facebook', 'sale_price', 'sale_price_effective_date',
                'item_group_id', 'gender', 'color', 'size', 'age_group', 'material',
                'pattern', 'shipping', 'shipping_weight', 'gtin', 'mpn'
            ];
            
            fputcsv($handle, $headers);
            
            $currencyCode = get_system_currency()->code;
            $processedCount = 0;
            
            foreach ($products as $product) {
                try {
                    $price = $product->unit_price;
                    if ($product->discount > 0 && $product->discount_type == 'percent') {
                        $price = $product->unit_price - ($product->unit_price * $product->discount / 100);
                    } elseif ($product->discount > 0 && $product->discount_type == 'amount') {
                        $price = $product->unit_price - $product->discount;
                    }
                    
                    $formattedPrice = number_format($price, 2, '.', '') . ' ' . $currencyCode;
                    $productUrl = route('product', $product->slug);
                    $imageUrl = asset($product->thumbnail_img);
                    
                    $row = [
                        'id' => (string) $product->id,
                        'title' => $this->cleanCsvField($product->getTranslation('name')),
                        'description' => $this->cleanCsvField(substr(strip_tags($product->getTranslation('description')), 0, 5000)),
                        'availability' => ($product->current_stock > 0) ? 'in stock' : 'out of stock',
                        'condition' => 'new',
                        'price' => $formattedPrice,
                        'link' => $productUrl,
                        'image_link' => $imageUrl,
                        'brand' => $this->cleanCsvField($product->brand->name ?? 'Generic'),
                        'google_product_category' => $this->getGoogleProductCategory($product->main_category->name ?? ''),
                        'fb_product_category' => $this->getFacebookProductCategory($product->main_category->name ?? ''),
                        'quantity_to_sell_on_facebook' => (int) max(0, $product->current_stock),
                        'sale_price' =>  $formattedPrice,
                        'sale_price_effective_date' => '',
                        'item_group_id' => '',
                        'gender' => '',
                        'color' => '',
                        'size' => '',
                        'age_group' => '',
                        'material' => '',
                        'pattern' => '',
                        'shipping' => '',
                        'shipping_weight' => '',
                        'gtin' => '',
                        'mpn' => $product->sku ?? (string) $product->id
                    ];
                    
                    fputcsv($handle, $row);
                    $processedCount++;
                    
                } catch (\Exception $e) {
                    Log::error('Error processing product for Facebook CSV', [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);
            
            $response = response($csvContent, 200);
            $response->header('Content-Type', 'text/csv; charset=UTF-8');
            $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Facebook CSV generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'CSV generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportProducts(Request $request)
    {
        try {
            $request->validate(['format' => 'required|in:xml,csv,txt']);

            $products = Product::with(['brand', 'main_category'])
                ->where('published', 1)
                ->where('approved', 1)
                ->where('draft', 0)
                ->where('facebook_catalogue', 1)
                ->limit(5000)
                ->get();

            if($request->product_ids) {
                $products = $products->whereIn('id', $request->product_ids);
            }

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
            Log::error('exportAllProducts for Facebook failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function get_catalogue_products(Request $request)
    {
        $col_name = null;
        $query = null;
        $sort_search = null;
        
        $products = Product::where('auction_product', 0)
            ->where('wholesale_product', 0)
            ->where('draft', 0)
            ->where('facebook_catalogue', 1)
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
            $products = $products->where('name', 'like', '%' . $sort_search . '%');
        }
        
        if ($request->type != null) {
            $var = explode(",", $request->type);
            $col_name = $var[0];
            $query = $var[1];
            $products = $products->orderBy($col_name, $query);
        }

        $products = $products->orderBy('created_at', 'desc')->paginate(15);
        
        $type = $request->seller_type;
        $view = view('backend.catalogue.products_table',
            compact('products', 'type', 'col_name', 'query', 'sort_search')
        )->render();

        return response()->json(['html' => $view]);
    }

    public function productUpdateToCatalog(Request $request)
    {
        $productIds = $request->id ?? $request->ids;
        
        if (!$productIds && $request->facebook_catalogue) {
            return response()->json([
                'success' => false, 
                'message' => translate('No products selected')
            ], 400);
        }
        
        if (is_string($productIds)) {
            $productIds = explode(',', $productIds);
        }
        
        if (!isset($request->facebook_catalogue)) {
            Product::where('facebook_catalogue', 1)->update(['facebook_catalogue' => 0]);
            if (!empty($productIds)) {
                Product::whereIn('id', $productIds)->update(['facebook_catalogue' => 1]);
            }
        } else {
            Product::whereIn('id', $productIds)->update(['facebook_catalogue' => $request->facebook_catalogue]);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Facebook Catalog products updated successfully')
        ]);
    }

    public function catalogue_products_search(Request $request)
    {
        $facebook_catalogue = 1;
        $products = Product::where('auction_product', 0)
            ->where('wholesale_product', 0)
            ->where('draft', 0)
            ->where('published', 1)
            ->where('approved', 1);
        
        if($request->search_key != null){
            $products = $products->where('name', 'like', '%' . $request->search_key . '%');
        }
        
        if($request->category != null){
            $category = Category::with('childrenCategories')->find($request->category);
            $products = $category->products();
        }
        
        $products = $products->get();
        $single_select = $request->single_select ?? 0;
        
        return view('partials.product.products_search', compact('products', 'single_select', 'facebook_catalogue'));
    }

    public function xmlFeed()
    {
        $products = Product::where('auction_product', 0)
            ->where('wholesale_product', 0)
            ->where('draft', 0)
            ->where('facebook_catalogue', 1)
            ->where('published', 1)
            ->where('approved', 1)
            ->get();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>' . config('app.name') . ' - Facebook Catalog</title>';
        $xml .= '<link>' . url('/') . '</link>';
        $xml .= '<description>Product Feed for Meta Facebook Catalog</description>';
        
        foreach($products as $product) {
            $xml .= '<item>';
            $xml .= '<g:id>' . $product->id . '</g:id>';
            $xml .= '<title>' . $this->clean($product->name) . '</title>';
            $xml .= '<description>' . $this->clean(substr($product->description, 0, 5000)) . '</description>';
            $xml .= '<g:link>' . url('/product/' . $product->slug) . '</g:link>';
            $xml .= '<g:image_link>' . asset($product->thumbnail_img) . '</g:image_link>';
            $xml .= '<g:price>' . $product->unit_price . ' ' . get_system_currency()->symbol . '</g:price>';
            $xml .= '<g:availability>' . ($product->current_stock > 0 ? 'in stock' : 'out of stock') . '</g:availability>';
            $xml .= '<g:brand>' . ($product->brand->name ?? 'Generic') . '</g:brand>';
            $xml .= '<g:condition>new</g:condition>';
            $xml .= '<g:mpn>' . ($product->sku ?? $product->id) . '</g:mpn>';
            $xml .= '<g:product_type>' . $this->clean($product->main_category->name ?? 'Uncategorized') . '</g:product_type>';
            $xml .= '</item>';
        }
        
        $xml .= '</channel></rss>';
        
        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    public function manualPushToCatalog(Request $request)
    {
        try {
            $products = Product::where('auction_product', 0)
                ->where('wholesale_product', 0)
                ->where('draft', 0)
                ->where('facebook_catalogue', 1)
                ->where('published', 1)
                ->where('approved', 1)
                ->get();

            if($request->id) {
                $products = $products->whereIn('id', $request->id);
            }
            
            $successCount = 0;
            $failedCount = 0;
            
            foreach($products as $product) {
                $response = $this->sendToFacebookAPI($product);
                
                if($response && isset($response['success']) && $response['success']) {
                    $successCount++;
                } else {
                    $failedCount++;
                    Log::error('Facebook Catalog push failed for product: ' . $product->id);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Sync complete! Success: {$successCount}, Failed: {$failedCount}"
            ]);
            
        } catch(\Exception $e) {
            Log::error('Facebook Catalog sync error: ' . $e->getMessage());
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
            $response = $this->sendToFacebookAPI($product);
            
            if($response && isset($response['success']) && $response['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product pushed to Facebook Catalog successfully!'
                ]);
            }
            
            $errorMessage = $response['error'] ?? 'No response from Facebook API';
            return response()->json([
                'success' => false,
                'message' => 'Push failed: ' . $errorMessage
            ], 400);
            
        } catch(\Exception $e) {
            \Log::error('Push to Facebook error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    private function sendToFacebookAPI($product)
    {
        if(!$this->catalogueId || !$this->accessToken) {
            Log::error('Facebook Catalog ID or Access Token missing');
            return ['success' => false, 'error' => 'Catalog ID or Access Token missing'];
        }
        
        $url = "https://graph.facebook.com/v18.0/{$this->catalogueId}/products";
        
        $price = $product->unit_price;
        $currencyCode = get_system_currency()->code;
        
        if ($product->discount > 0 && $product->discount_type == 'percent') {
            $price = $product->unit_price - ($product->unit_price * $product->discount / 100);
        } elseif ($product->discount > 0 && $product->discount_type == 'amount') {
            $price = $product->unit_price - $product->discount;
        }
        
        $numericPrice = (int) round($price);
    $domain = env('APP_URL', url('/'));
        
    $productUrl = $domain . '/product/' . $product->slug;
    $imageUrl = $domain . '/public/uploads/all/' . $product->thumbnail_img;
        $data = [
            'retailer_id'  => (string) $product->id,
            'name'         => $this->clean($product->name),
            'description'  => $this->clean(substr($product->description, 0, 5000)),
            'availability' => ($product->current_stock > 0) ? 'in stock' : 'out of stock',
            'price'        => $numericPrice*100,
            'currency'     => $currencyCode,
            'url'          => $productUrl,
            'image_url'    => $imageUrl,
            'brand'        => $product->brand->name ?? 'Generic',
            'condition'    => 'new',
            'inventory'    => (int) $product->current_stock,
            'access_token' => $this->accessToken
        ];
        
        Log::info('Sending to Facebook API', [
            'url' => $url,
            'retailer_id' => $product->id,
            'price' => $numericPrice
        ]);
        
        try {
            $response = Http::asForm()->post($url, $data);
            $result = $response->json();
            
            Log::info('Facebook API Response', [
                'status' => $response->status(),
                'body'   => $result
            ]);
            
            if($response->successful() && isset($result['id'])) {
                return ['success' => true, 'product_id' => $result['id']];
            }
            
            $errorMsg = $result['error']['message'] ?? 'Unknown error';
            return ['success' => false, 'error' => $errorMsg];
            
        } catch(\Exception $e) {
            Log::error('Facebook API Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    private function getGoogleProductCategory($category)
    {
        $mapping = [
            'Electronics' => 'Electronics',
            'Mobiles & Tabs' => 'Electronics > Communications > Telephony > Mobile Phones',
            'Computers & Accessories' => 'Electronics > Computers',
            'Womens World' => 'Apparel & Accessories > Clothing',
            'Jewellery & Watches' => 'Apparel & Accessories > Jewelry',
            'Kids & Toys' => 'Toys & Games',
            'Home & Garden' => 'Home & Garden',
            'Automobiles' => 'Vehicles & Parts',
            'Pet Supplies' => 'Animals > Pet Supplies'
        ];
        
        return $mapping[$category] ?? 'Other';
    }

    private function getFacebookProductCategory($category)
    {
        $mapping = [
            'Electronics' => 'Electronics',
            'Mobiles & Tabs' => 'Electronics',
            'Computers & Accessories' => 'Electronics',
            'Womens World' => 'Clothing & Accessories > Clothing',
            'Jewellery & Watches' => 'Clothing & Accessories > Jewelry & Watches',
            'Kids & Toys' => 'Toys & Games',
            'Home & Garden' => 'Home & Garden',
            'Automobiles' => 'Vehicles & Parts',
            'Pet Supplies' => 'Animals > Pet Supplies'
        ];
        
        return $mapping[$category] ?? 'Other';
    }

    private function cleanCsvField($string)
    {
        if (empty($string)) {
            return '';
        }
        
        $string = strip_tags($string);
        $string = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        $string = trim($string);
        
        return $string;
    }

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

    private function clean($string)
    {
        return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
    }

    public function catalogue_configuration(Request $request)
    {
        return view('backend.setup_configurations.facebook_configuration.catalogue_configuration');
    }
}