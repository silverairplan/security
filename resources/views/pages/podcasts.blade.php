@extends('layouts.app', ['page' => __('Icons'), 'pageSlug' => 'icons'])

@section('content')
	<div class="content">
		<div class="container-fluid">
			<div class="row">
				<div class="col-md-12">
					<div class="card">
						<div class="card-header card-header-primary">
							<h4 class="card-title">Podcasts</h4>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table class="table">
									<thead class="text-primary">
										<th></th>
										<th>Feed Url</th>
										<th>Title</th>
										<th>Description</th>
										<th>Author</th>
										<th>Creater</th>
										<th>Created Date</th>
									</thead>
									<tbody>
										@foreach($podcasts as $podcast)
										<tr>
											<td>
												<img src="{{$podcast->image}}">
											</td>
											<td>{{$podcast->feedurl}}</td>
											<td>{{$podcast->title}}</td>
											<td>{{$podcast->description}}</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection