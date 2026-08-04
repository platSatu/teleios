@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="card card-h-100">
            <!--start::card-->
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Branch Office</h5>

                <a href="{{ route('user-settings.branch-offices.create') }}" class="btn btn-secondary rounded-pill">
                    <i class="ri-add-line me-1"></i>
                    Tambah Data
                </a>
            </div>

            <div class="card-body">
                @include('components.notifikasi')

                <form method="GET" class="input-group mb-3">
                    <input type="text" name="search" class="form-control" placeholder="Search"
                        value="{{ request('search') }}" />
                    <button type="submit" class="btn btn-light">
                        <i class="ri-search-2-line"></i>
                    </button>
                </form>

                <!-- start:: Borderedless Table -->
                <div class="table-responsive" data-simplebar>
                    <table class="table text-nowrap mb-0">
                        <thead>
                            <tr>
                                <th width="80">No</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th width="100">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($branchOffices as $branchOffice)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>

                                    <td>{{ $branchOffice->name }}</td>

                                    <td>{{ $branchOffice->address ?? '-' }}</td>

                                    <td>
                                        @if ($branchOffice->status == 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-light-primary icon-btn" type="button"
                                                data-bs-toggle="dropdown">
                                                <i class="ri-more-2-line"></i>
                                            </button>

                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item"
                                                        href="{{ route('user-settings.branch-offices.edit', $branchOffice->id) }}">
                                                        <i class="ri-edit-2-line me-2"></i>
                                                        Edit
                                                    </a>
                                                </li>

                                                <li>
                                                    <form
                                                        action="{{ route('user-settings.branch-offices.destroy', $branchOffice->id) }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('DELETE')

                                                        <button class="dropdown-item text-danger"
                                                            onclick="return confirm('Delete this branch office?')">
                                                            <i class="ri-delete-bin-line me-2"></i>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">
                                        No branch office data found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- end:: Borderedless Table -->

                <div class="mt-3">
                    {{ $branchOffices->links() }}
                </div>
            </div>
        </div>
        <!--end::card-->
    </div>
@endsection
