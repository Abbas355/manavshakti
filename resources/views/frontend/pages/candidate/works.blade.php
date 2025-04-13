@extends('frontend.layouts.app')

@section('title')
    {{ __('Works') }}
@endsection

@section('main')
    <div class="dashboard-wrapper">
        <div class="container">
            <div class="row">
                <x-website.candidate.sidebar />
                <div class="col-lg-9">
                    <div class="dashboard-right">
                        <div class="dashboard-right-header rt-mb-32 tw-mt-4 lg:tw-mt-0">
                            <div class="left-text m-0">
                                <h3 class="f-size-18 lh-1 m-0">
                                    {{ __('Works') }}
                                    <span class="text-gray-400">({{ $totalFilteredJobs }})</span>
                                </h3>
                            </div>
                            <span class="sidebar-open-nav">
                                <i class="ph-list"></i>
                            </span>
                        </div>

                        <div class="row mt-5">
                            <h5>{{ __('latest_jobs') }}</h5>

                            @php
                                $mix_jobs = isset($all_jobs) && count($all_jobs) ? $all_jobs : $jobs;
                                $jobId = 0;
                            @endphp

                            @forelse ($mix_jobs as $job)
                                <div class="col-xl-4 col-md-6 fade-in-bottom rt-mb-24 cat-1 cat-3">
                                    <x-website.job.job-card :job="$job" />
                                </div>
                                @php
                                    $jobId = isset($job->id) ? $job->id : 0;
                                @endphp
                            @empty
                                <div class="col-12" id="loading-spinner">
                                    <div class="card text-center">
                                        <x-not-found message="{{ __('no_data_found') }}" />
                                    </div>
                                </div>
                            @endforelse

                            <div id="mix-job" class="row"></div>

                            @if (!$mix_jobs->isEmpty())
                                <button id="load-more-button" data-page="1" data-id="{{ $jobId }}"
                                    class="newsButton btn btn-primary px-4 py-2 m-auto">
                                    {{ __('load_more') }}
                                </button>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="dashboard-footer text-center body-font-4 text-gray-500">
            <x-website.footer-copyright />
        </div>
    </div>
@endsection

@section('script')
    <script>
        function loadMoreJobs() {
            let currentUrl = window.location.href;
            let urlWithoutQueryString = currentUrl.split('?')[0];
            let queryString = window.location.search;

            let id = parseInt(document.getElementById('load-more-button').getAttribute('data-id'));
            let page = parseInt(document.getElementById('load-more-button').getAttribute('data-page'));

            // Extract existing "keyword" and "location" parameters from the query string
            let searchParams = new URLSearchParams(queryString);
            let existingKeyword = searchParams.get('keyword');
            let existingLocation = searchParams.get('location');

            // Convert null values to empty strings if they are null
            existingKeyword = existingKeyword === null ? '' : existingKeyword;
            existingLocation = existingLocation === null ? '' : existingLocation;

            // Construct the updated query string with all parameters
            let updatedQueryString = `?page=${page}&id=${id}&keyword=${existingKeyword}&location=${existingLocation}`;
            let newUrl = `${urlWithoutQueryString.replace('/candidate/works', '/loadmore')}${updatedQueryString}`;

    

            $('#load-more-button').prop('disabled', true).text('Loading...');
            axios.get(newUrl).then((response) => {
                $('#mix-job').append(response.data);
                $('#load-more-button').prop('disabled', false).text('Load More');
                let newId = parseInt(document.getElementById('get-id-page').getAttribute('data-id'));
                document.getElementById('load-more-button').setAttribute('data-id', newId);
                if (newId == 0) {
                    document.getElementById('load-more-button').setAttribute('data-page', page + 1);
                }
                $('#get-id-page').remove();
            }).catch((error) => {
                $('#load-more-button').prop('disabled', true).text('No jobs found').removeClass('btn-primary')
                    .addClass('btn-secondary');
            })
        }

        $(document).ready(function() {
            $('#load-more-button').click(function(e) {
                e.preventDefault();
                loadMoreJobs();
            });
        });
    </script>

    
@endsection
