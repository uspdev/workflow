@extends('layouts.app')

@section('content')
  <div class="mt-2">
    <h2>Atendimentos</h2>
    <p>Estes são os requerimentos que estão em um estado relacionado a você, indicado por seus atendimentos.<br>
    Para gerenciar um requerimento, clique em seu ID.</p>
    <table class="table datatable-simples responsive table-stripped table-sm table-bordered table-hover mb-3">
      <thead>
        <tr>
          <th>ID</th>
          <th>Estado Atual</th>
          <th>Definição do Workflow</th>
          <th>Autor</th>
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
              @foreach ($workflowObject->state as $state => $number)
                <span class="badge  bg-{{ $badgeColor }}">{{ $state }}</span>
              @endforeach
            </td>
            <td>{{ $workflowsDisplay['workflowData'][$workflowObject->id]['workflowDefinition']['definition']['title'] }}</td>
            <td>{{ $workflowsDisplay['workflowData'][$workflowObject->id]['user']->name }}</td>
            <td>{{ \Carbon\Carbon::parse($workflowObject->created_at)->format('d/m/Y H:i') }}</td>
            <td>{{ \Carbon\Carbon::parse($workflowObject->updated_at)->format('d/m/Y H:i') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
