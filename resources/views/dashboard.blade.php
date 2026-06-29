<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Intelligence Overview
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- KPI Strip --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow p-5">
                    <div class="text-sm text-gray-500">Connected Sources</div>
                    <div class="text-3xl font-bold text-blue-600 mt-1">{{ $connectedCount }}</div>
                </div>
                <div class="bg-white rounded-xl shadow p-5">
                    <div class="text-sm text-gray-500">Open Alerts</div>
                    <div class="text-3xl font-bold text-red-500 mt-1">{{ $openAlertCount }}</div>
                </div>
                <div class="bg-white rounded-xl shadow p-5">
                    <div class="text-sm text-gray-500">Pending Actions</div>
                    <div class="text-3xl font-bold text-amber-500 mt-1">{{ $pendingRecommendationCount }}</div>
                </div>
                <div class="bg-white rounded-xl shadow p-5">
                    <div class="text-sm text-gray-500">Intelligence Engine</div>
                    <div class="text-sm font-semibold text-green-600 mt-2">Active</div>
                </div>
            </div>

            {{-- Intelligence Query --}}
            <livewire:analytics.intelligence-dashboard />

            {{-- Alerts + Recommendations --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <livewire:analytics.alerts-panel />
                <livewire:analytics.recommendations-panel />
            </div>

            {{-- Data Sources --}}
            <livewire:analytics.data-source-panel />
        </div>
    </div>
</x-app-layout>
