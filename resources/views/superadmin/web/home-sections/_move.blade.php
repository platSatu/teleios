{{-- Tombol naik/turun urutan. $route = nama route move, $id, $first, $last. --}}
<div class="btn-group btn-group-sm">
    <form action="{{ route($route, [$id, 'up']) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-secondary" title="Naikkan" @disabled($first)><i class="ri-arrow-up-line"></i></button>
    </form>
    <form action="{{ route($route, [$id, 'down']) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-secondary" title="Turunkan" @disabled($last)><i class="ri-arrow-down-line"></i></button>
    </form>
</div>
