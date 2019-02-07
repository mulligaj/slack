$(function(){
	$('a[data-nonceurl]').on('click', function(e){
		e.preventDefault();
		var nonceUrl = $(this).data('nonceurl');
		var slackUrl = $(this).attr('href');
		$.ajax({
			url: nonceUrl,
			success: function(response){
				var redirectUrl = slackUrl + '&state=' + response.nonce;
				window.location.replace(redirectUrl);
			}
		});
	});
});
