@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="card card-h-100">
            <!--start::card-->
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Data List</h5>

                <a href="{{ route('company.create') }}" class="btn btn-secondary rounded-pill">
                    <i class="ri-add-line me-1"></i>
                    Tambah Data
                </a>
            </div>
            
            <div class="card-body">
                @include('components.notifikasi')
                <div class="input-group mb-3">
                    <input class="search form-control" placeholder="Search" />
                    <button type="button" class="sort btn btn-light" data-sort="name">
                        <i class="ri-search-2-line"></i>
                    </button>
                </div>

                <!-- start:: Borderedless Table -->
                <div class="table-responsive" data-simplebar>
                    <table class="table text-nowrap mb-0">
                        <thead>
                            <tr>
                                <th width="80">No</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th width="100">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($companies as $company)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>

                                    <td>{{ $company->name }}</td>

                                    <td>
                                        @if ($company->status == 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>

                                    <td>{{ $company->user->name ?? '-' }}</td>

                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-light-primary icon-btn" type="button"
                                                data-bs-toggle="dropdown">
                                                <i class="ri-more-2-line"></i>
                                            </button>

                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item"
                                                        href="{{ route('company.edit', $company->id) }}">
                                                        <i class="ri-edit-2-line me-2"></i>
                                                        Edit
                                                    </a>
                                                </li>

                                                <li>
                                                    <form action="{{ route('company.destroy', $company->id) }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('DELETE')

                                                        <button class="dropdown-item text-danger"
                                                            onclick="return confirm('Delete this company?')">
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
                                        No company data found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                </div>
                <!-- end:: Borderedless Table -->
            </div>
        </div>
        <!--end::card-->
    </div>
@endsection
