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
