{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>{{ route('home') }}</loc></url>@foreach($jobs as $job)<url><loc>{{ route('jobs.show',$job) }}</loc><lastmod>{{ $job->updated_at->toAtomString() }}</lastmod></url>@endforeach</urlset>

