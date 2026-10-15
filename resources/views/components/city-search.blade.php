@props(['name' => 'location', 'value' => null, 'compact' => false])
<div @class(['city-search', 'city-search-compact' => $compact]) data-city-search data-url="{{ route('locations.cities') }}">
    <x-field :name="$name" label="Kota / kabupaten" :value="$value" placeholder="Cari kota atau kabupaten" maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" :aria-controls="$name.'-cities'" :aria-describedby="$name.'-city-status'" data-city-input />
    <ul id="{{ $name }}-cities" class="city-options" role="listbox" aria-label="Saran kota dan kabupaten" hidden></ul>
    <p id="{{ $name }}-city-status" class="city-search-status" role="status" aria-live="polite" hidden></p>
</div>
