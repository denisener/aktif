<!doctype html>
@if(\App\Models\Language::where('code', Session::get('locale', Config::get('app.locale')))->first()->rtl == 1)
<html dir="rtl" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@else
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@endif
<head>
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<meta name="app-url" content="{{ getBaseURL() }}">
	<meta name="file-base-url" content="{{ getFileBaseURL() }}">

	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<!-- Favicon -->
	<link rel="icon" href="{{ uploaded_asset(get_setting('site_icon')) }}">
	<title>{{ get_setting('website_name').' | '.get_setting('site_motto') }}</title>

	<!-- google font -->
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700">

	<!-- aiz core css -->
	<link rel="stylesheet" href="{{ static_asset('assets/css/vendors.css') }}">
    @if(\App\Models\Language::where('code', Session::get('locale', Config::get('app.locale')))->first()->rtl == 1)
    <link rel="stylesheet" href="{{ static_asset('assets/css/bootstrap-rtl.min.css') }}">
    @endif
	<link rel="stylesheet" href="{{ static_asset('assets/css/aiz-seller.css') }}">
    <link rel="stylesheet" href="{{ static_asset('assets/css/custom-style.css') }}">
    <link rel="stylesheet" href="{{ static_asset('assets/css/seller-custom-style.css') }}">

    <style>
        body {
            font-size: 12px;
            font-family: {!! !empty(get_setting('system_font_family')) ? get_setting('system_font_family') : "'Public Sans', sans-serif" !!}, sans-serif;
        }
        #map{
            width: 100%;
            height: 250px;
        }
        #edit_map{
            width: 100%;
            height: 250px;
        }
        .pac-container{
            z-index: 100000;
        }

        .footer-email-input::placeholder{
            color: {{ get_setting('footer_text_color') }} !important;
            opacity: 0.5;
        }

        .header-content-wrapper .nav-menu-list .nav-item .nav-link:hover,
        .header-content-wrapper .add-to-cart:hover{
            color: {{ get_setting('top_header_text_color') }} !important;
            opacity: 0.5;
        }

        .login-nav-item:hover .user-icon-circle{
            border-color: {{ get_setting('top_header_text_color') }} !important;
            box-shadow: 0px 6px 10px rgba(0, 0, 0, 0.16);
        }

        .login-nav-item:hover .user-icon-circle i{
            color:  {{ get_setting('top_header_text_color') }} !important;
        }
    </style>
	<script>
    	var AIZ = AIZ || {};
        AIZ.local = {
            nothing_selected: '{!! translate('Nothing selected', null, true) !!}',
            nothing_found: '{!! translate('Nothing found', null, true) !!}',
            choose_file: '{{ translate('Choose file') }}',
            file_selected: '{{ translate('File selected') }}',
            files_selected: '{{ translate('Files selected') }}',
            add_more_files: '{{ translate('Add more files') }}',
            adding_more_files: '{{ translate('Adding more files') }}',
            drop_files_here_paste_or: '{{ translate('Drop files here, paste or') }}',
            browse: '{{ translate('Browse') }}',
            upload_complete: '{{ translate('Upload complete') }}',
            upload_paused: '{{ translate('Upload paused') }}',
            resume_upload: '{{ translate('Resume upload') }}',
            pause_upload: '{{ translate('Pause upload') }}',
            retry_upload: '{{ translate('Retry upload') }}',
            cancel_upload: '{{ translate('Cancel upload') }}',
            uploading: '{{ translate('Uploading') }}',
            processing: '{{ translate('Processing') }}',
            complete: '{{ translate('Complete') }}',
            file: '{{ translate('File') }}',
            files: '{{ translate('Files') }}',
            saving: '{{ translate('Saving') }}',
            something_went_wrong: '{{translate('Something went wrong!')}}',
            error_occured_while_processing: '{{translate('An error occurred while processing')}}',
            saving_as_draft: '{{translate('Saving As Draft')}}',
        }
	</script>

</head>
<body class="">

	<div class="aiz-main-wrapper">
        @include('seller.inc.seller_sidenav')
		<div class="aiz-content-wrapper">
            @include('seller.inc.seller_nav')
			<div class="aiz-main-content">
				<div class="px-15px px-lg-25px">
                    @yield('panel_content')
				</div>
				<div class="bg-white text-center py-3 px-15px px-lg-25px mt-auto border-sm-top ">

                    <div class="d-flex justify-content-center flex-wrap">
                        <a href="{{ route('home') }}/seller-legal-notice" target="_blank" class="btn btn-link p-0 text-decoration-none mr-3 fw-700 fs-12 hov-text-info">
                            {{ translate('Legal Notice') }}
                        </a>
                        <a href="{{ route('home') }}/seller-withdrawal-policy" target="_blank" class="btn btn-link p-0 text-decoration-none mr-3 fw-700 fs-12 hov-text-info">
                            {{ translate('Right of Withdrawal') }}
                        </a>
                        <a href="{{ route('home') }}/seller-terms-and-conditions" target="_blank" class="btn btn-link p-0 text-decoration-none mr-3 fw-700 fs-12 hov-text-info">
                            {{ translate('Terms & Conditions') }}
                        </a>
                        <a href="{{ route('home') }}/seller-policy" target="_blank" class="btn btn-link p-0 text-decoration-none mr-3 fw-700 fs-12 hov-text-info">
                            {{ translate('Seller Privacy Policy') }}
                        </a>
                    </div>

                    <p class="mb-0 mt-2 fs-11">
                        &copy; {{ get_setting('site_name') }} v{{ get_setting('current_version') }}
                    </p>

                </div>
			</div><!-- .aiz-main-content -->
		</div><!-- .aiz-content-wrapper -->
	</div><!-- .aiz-main-wrapper -->

    @include('modals.bulk_action_modal')

    <!-- Offcanvas -->
    <div id="globalRightOffcanvas" class="global-right-offcanvas-lg position-fixed top-0 fullscreen bg-white  py-20px z-1045">
        <!-- content will here -->
    </div>
    <!-- Overlay -->
    <div id="globalRightOffcanvasOverlay" class="position-fixed top-0 left-0 h-100 w-100"></div>
    
    @yield('modal')


	<script src="{{ static_asset('assets/js/vendors.js') }}" ></script>
	<script src="{{ static_asset('assets/js/aiz-core.js') }}" ></script>
    <script src="{{ static_asset('assets/js/aiz-form-submission.js?v=') }}{{ rand(1000,9999) }}"></script>

    @yield('script')

    <script type="text/javascript">
	    @foreach (session('flash_notification', collect())->toArray() as $message)
	        AIZ.plugins.notify('{{ $message['level'] }}', '{{ $message['message'] }}');
            @if ($message['message'] == translate('Product has been inserted successfully'))
                var data_type = ['digital', 'physical', 'auction', 'wholesale'];
                data_type.forEach(element => {
                    localStorage.setItem('tempdataproduct_'+element, '{}');
                    localStorage.setItem('tempload_'+element, 'no');
                });
            @endif
	    @endforeach

        $('.dropdown-menu a[data-toggle="tab"]').click(function(e) {
            e.stopPropagation()
            $(this).tab('show')
        })

        if ($('#lang-change').length > 0) {
            $('#lang-change .dropdown-menu a').each(function() {
                $(this).on('click', function(e){
                    e.preventDefault();
                    var $this = $(this);
                    var locale = $this.data('flag');
                    $.post('{{ route('language.change') }}',{_token:'{{ csrf_token() }}', locale:locale}, function(data){
                        location.reload();
                    });

                });
            });
        }
        function menuSearch(){
			var filter, item;
			filter = $("#menu-search").val().toUpperCase();
			items = $("#main-menu").find("a");
			items = items.filter(function(i,item){
				if($(item).find(".aiz-side-nav-text")[0].innerText.toUpperCase().indexOf(filter) > -1 && $(item).attr('href') !== '#'){
					return item;
				}
			});

			if(filter !== ''){
				$("#main-menu").addClass('d-none');
				$("#search-menu").html('')
				if(items.length > 0){
					for (i = 0; i < items.length; i++) {
						const text = $(items[i]).find(".aiz-side-nav-text")[0].innerText;
						const link = $(items[i]).attr('href');
						 $("#search-menu").append(`<li class="aiz-side-nav-item"><a href="${link}" class="aiz-side-nav-link"><i class="las la-ellipsis-h aiz-side-nav-icon"></i><span>${text}</span></a></li`);
					}
				}else{
					$("#search-menu").html(`<li class="aiz-side-nav-item"><span	class="text-center text-muted d-block">{{ translate('Nothing Found') }}</span></li>`);
				}
			}else{
				$("#main-menu").removeClass('d-none');
				$("#search-menu").html('')
			}
        }

        const globalRightOffcanvas = document.getElementById('globalRightOffcanvas');
        const globalOverlay = document.getElementById('globalRightOffcanvasOverlay');

        $(document).on('click', '#add_note', function (e) {
            e.preventDefault();
            const noteType = $(this).data('note-type') || 'refund';
            openGlobalRightOffcanvas('note', noteType);
        });

        function openGlobalRightOffcanvas(type = 'note', extraParam = null) {
            globalRightOffcanvas.classList.add('active');
            globalOverlay.classList.add('active');
            document.body.classList.add('body-no-scroll');
            globalRightOffcanvas.innerHTML =
                '<div class="footable-loader mt-5"><span class="fooicon fooicon-loader"></span></div>';

            const urls = {
                note: "{{ route('seller.ajax_add_note_modal') }}",
            };

            const postData = { _token: AIZ.data.csrf };
            if (type === 'note' && extraParam) {
                postData.type = extraParam;
            }

            $.ajax({
                type: "POST",
                url: urls[type],
                data: postData,
                success: function (html) {
                    globalRightOffcanvas.innerHTML = html;
                    AIZ.plugins.bootstrapSelect('refresh');

                    $('#globalRightOffcanvas .aiz-date-range').each(function() {
                        var $input = $(this);
                        var separator = $input.data('separator') || ' to ';
                        var format = $input.data('format') || 'DD-MM-Y';
                        var timePicker = $input.data('time-picker') || false;
                        var pastDisable = $input.data('past-disable') || false;

                        if ($input.data('daterangepicker')) {
                            $input.data('daterangepicker').remove();
                        }

                        $input.daterangepicker({
                            timePicker: timePicker,
                            autoUpdateInput: false,
                            minDate: pastDisable ? moment() : false,
                            locale: {
                                format: format,
                                separator: separator,
                            }
                        });

                        $input.on('apply.daterangepicker', function(ev, picker) {
                            $(this).val(picker.startDate.format(format) + separator + picker.endDate.format(format));
                        });

                        $input.on('cancel.daterangepicker', function() {
                            $(this).val('');
                        });
                    });
                },
                error: function () {
                    globalRightOffcanvas.innerHTML =
                        '<p class="text-danger p-3">{{ translate("Failed to load") }}</p>';
                }
            });
        }

        function closeglobalRightOffcanvas() {
            globalRightOffcanvas.classList.remove('active');
            globalOverlay.classList.remove('active');
            document.body.classList.remove('body-no-scroll');
        }

        function closeGlobalRightOffcanvas() {
            closeglobalRightOffcanvas();
        }

        if (globalOverlay) {
            globalOverlay.addEventListener('click', closeglobalRightOffcanvas);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeglobalRightOffcanvas();
            }
        });

        $(document).on('click', '#add-note', function () {
            const btn = $(this);
            const noteType = $('select[name="note_type"]').val();
            const description = $('textarea[name="note_description"]').val();

            if (!description || !description.trim()) {
                AIZ.plugins.notify('warning', '{{ translate("Please fill description") }}');
                return;
            }

            btn.prop('disabled', true);
            if (!btn.find('.spinner-border').length) {
                btn.append('<span class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span>');
            }

            $.ajax({
                url: "{{ route('seller.ajax_add_note_store') }}",
                type: "POST",
                data: {
                    _token: AIZ.data.csrf,
                    note_type: noteType,
                    description: description
                },
                success: function (res) {
                    if (!res.success) return;

                    AIZ.plugins.notify('success', res.message);

                    const wrapperMap = {
                        refund:   '.refundable-notes',
                        warranty: '.warranty-notes',
                        shipping: '.shipping-notes',
                        delivery: '.cash-on-delivery-notes',
                    };
                    const hiddenInputMap = {
                        refund:   'refund_note_id',
                        warranty: 'warranty_note_id',
                        shipping: 'shipping_note_id',
                        delivery: 'delivery_note_id',
                    };

                    const wrapperSelector = wrapperMap[noteType];
                    const hiddenInputId   = hiddenInputMap[noteType];
                    const $wrapper        = $(wrapperSelector);
                    const $carousel       = $wrapper.find('.aiz-carousel');

                    if ($carousel.hasClass('slick-initialized')) {
                        $carousel.slick('unslick');
                    }

                    $carousel.html(res.html);

                    AIZ.plugins.slickCarousel();

                    setTimeout(function () {
                        $wrapper.find('#' + hiddenInputId).val(res.note_id);

                        $carousel.find('[data-note-id]')
                            .removeClass('border-primary')
                            .addClass('border-gray-300');

                        $carousel.find('[data-note-id="' + res.note_id + '"]')
                            .removeClass('border-gray-300')
                            .addClass('border-primary');
                    }, 300);

                    closeglobalRightOffcanvas();
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON?.errors ?? {};
                        const first = Object.values(errors)[0]?.[0];
                        AIZ.plugins.notify('danger', first || '{{ translate("Validation failed") }}');
                    } else {
                        AIZ.plugins.notify('danger', '{{ translate("Something went wrong") }}');
                    }
                    btn.prop('disabled', false);
                    btn.find('.spinner-border').remove();
                }
            });
        });

    </script>

</body>
</html>
