@extends('layouts.app')

@section('title', 'Data Verification')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h1 class="text-2xl font-bold mb-6">Data Verification</h1>

        {{-- EXPORT EXCEL BUTTON --}}
<div class="flex justify-end mb-4">
    <a href="{{ route('promo.onedecade.dataverif.export.excel') }}"
       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-semibold">
        Export Excel
    </a>
</div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="text-3xl font-bold text-blue-700">{{ $totalVerifications }}</div>
                <div class="text-sm text-blue-700">Total Verifications</div>
            </div>
            
            @foreach($platformStats as $platform => $count)
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="text-3xl font-bold text-green-700">{{ $count }}</div>
                <div class="text-sm text-green-700">{{ ucfirst($platform) }}</div>
            </div>
            @endforeach
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Undian Code</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Order Number</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Platform</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact Info</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Entry Number</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Verified At</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($verifiedEntries as $entry)
                    <tr>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->id }}</td>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->undian_code }}</td>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->order_number }}</td>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->platform }}</td>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->contact_info }}</td>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->entry_number }}</td>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->verified_at }}</td>
                        <td class="py-2 px-4 border-b border-gray-200">{{ $entry->ip_address }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // You can add any JavaScript for the table here
        // For example, client-side sorting or filtering
    });
</script>
@endsection