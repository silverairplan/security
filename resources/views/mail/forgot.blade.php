<div>
	<p>Hi {{$user->fullname}}</p>
	<p>Please <a href="{{url('/reset/' . $user->token)}}">Click here</a> To Reset Password</p>
</div>