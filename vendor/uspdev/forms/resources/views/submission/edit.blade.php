@extends('uspdev-forms::layouts.app')

@section('content')
  <div class="card">
    <div class="card-header card-header-sticky d-flex justify-content-between align-items-center">
      <h4>
        USPdev forms > 
        <a href="{{ route('form-submissions.index', $definition) }}">{{ $definition->name }}</a> >

        @if ($submission)
          Editar
        @else
          Nova submissão
        @endif
        @include('uspdev-forms::submission.partials.index-btn')

      </h4>
      <div class="d-flex">
        @includeWhen($submission, 'uspdev-forms::submission.partials.delete-btn')
      </div>
    </div>
    <div class="card-body">
      {!! $formHtml !!}
    </div>
  </div>
@endsection
