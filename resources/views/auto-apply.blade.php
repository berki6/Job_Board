@extends('layouts.app')

@section('content')
    <div class="max-w-2xl mx-auto bg-white shadow-lg rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">AI Auto-Apply Settings</h2>

        <form method="POST" action="{{ route('auto.apply.update') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-gray-700">Preferred Job Titles</label>
                <input type="text" name="job_titles" value="{{ old('job_titles', json_encode($preferences->job_titles ?? [])) }}"
                    class="w-full border rounded p-2" placeholder='["Developer","Designer"]'>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Locations</label>
                <input type="text" name="locations" value="{{ old('locations', json_encode($preferences->locations ?? [])) }}"
                    class="w-full border rounded p-2" placeholder='["Remote","New York"]'>
            </div>

            <div class="mb-4 flex space-x-4">
                <div class="flex-1">
                    <label class="block text-gray-700">Salary Min</label>
                    <input type="number" name="salary_min" value="{{ $preferences->salary_min }}"
                        class="w-full border rounded p-2">
                </div>
                <div class="flex-1">
                    <label class="block text-gray-700">Salary Max</label>
                    <input type="number" name="salary_max" value="{{ $preferences->salary_max }}"
                        class="w-full border rounded p-2">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700">Cover Letter Template (optional)</label>
                <textarea name="cover_letter_template" class="w-full border rounded p-2"
                    rows="4">{{ $preferences->cover_letter_template }}</textarea>
            </div>

            <div class="flex justify-between">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Save Settings</button>
                <a href="{{ route('auto.apply.toggle') }}" class="bg-green-500 text-white px-4 py-2 rounded">
                    {{ $preferences->auto_apply_enabled ? 'Disable Auto-Apply' : 'Enable Auto-Apply' }}
                </a>
            </div>
        </form>
    </div>
@endsection