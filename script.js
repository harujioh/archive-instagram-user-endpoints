$(document).ready(function(){

	// erase access token
	if(location.hash.length > 0){
		var syntax = location.hash.split('=');
		if(syntax[0] == '#access_token'){
			$('#access_token').val(syntax[1]);
		}
	}

	$('#oauth-button').click(function(){
		var url = 'https://www.instagram.com/oauth/authorize/';
		var param = {
			client_id : $('#client_id').val(),
			redirect_uri : location.href,
			response_type : 'token'
		};

		location.href = url + '?' + $.param(param);
		return false;
	});
	
	var apiUrl = $('#archive-instagram').data('apiUrl');
	
	$('#archive-instagram li').click(function(){
		var $self = $(this);
		var flag = $(this).hasClass('disabled');

		$.getJSON(apiUrl, {
			id : $(this).data('id'),
			method : flag ? 'enabled' : 'disabled'
		}, function(json){
			if(flag){
				$self.removeClass('disabled');
			}else{
				$self.addClass('disabled');
			}
		});
	});
});