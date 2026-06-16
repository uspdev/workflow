@extends('layouts.app')

@section('content')
  <div class="mt-2">
    <h2>Meus requerimentos</h2>
    <p>Estes são os seus requerimentos. Para criar um novo requerimento, clique em "Novo requerimento, no menu superior".<br>
    Para gerenciar um requerimento, clique em seu ID.</p>
    <table class="table datatable-simples responsive table-stripped table-sm table-bordered table-hover mb-3">
      <thead>
        <tr>
          <th>ID do Workflow</th>
          <th>Estado Atual</th>
          <th>Definição do Workflow</th>
          <th>Criado em</th>
          <th>Ultima modificação</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($workflowsDisplay['workflows'] as $workflowObject)
          @php
            $badgeColor = 'secondary';
            
            if ($workflowsDisplay['workflowData'][$workflowObject->id]['state'] == 'start') {
                $badgeColor = 'warning';
            } elseif ($workflowsDisplay['workflowData'][$workflowObject->id]['state'] == 'progress') {
                $badgeColor = 'success';
            }
          @endphp
          <tr>
            <td>
              <a href="{{ route('workflows.showObject', $workflowObject->id) }}">
                {{ $workflowObject->id }}
              </a>
            </td>
            <td>
              <span class="badge bg-{{ $badgeColor }}">
                  {{ implode(', ', array_map(function($key, $value) {
                      return "$key";
                  }, array_keys($workflowObject->state), $workflowObject->state)) }}
              </span>
            </td>
            <td>{{ $workflowsDisplay['workflowData'][$workflowObject->id]['workflowDefinition']['definition']['title'] }}</td>
            <td>{{ \Carbon\Carbon::parse($workflowObject->created_at)->format('d/m/Y H:i') }}</td>
            <td>{{ \Carbon\Carbon::parse($workflowObject->updated_at)->format('d/m/Y H:i') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
