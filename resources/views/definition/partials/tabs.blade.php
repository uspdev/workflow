<ul class="nav nav-tabs">
  <li class="nav-item">
    <a class="nav-link {{ $activeTab == 'index' ? 'active':'' }}" href="{{ route('workflows.list-definitions') }}">Definitions</a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $activeTab == 'backup' ? 'active':'' }}" href="{{ route('workflows.backups-idx') }}">Backup</a>
  </li>
<br>