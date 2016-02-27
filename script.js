$(document).ready(function(){
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