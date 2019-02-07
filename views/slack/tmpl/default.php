<?php
	$slackUrl = 'https://slack.com/oauth/authorize?';
	$slackUrl .= 'client_id=367477798656.367950455284';
	$slackUrl .= '&scope=channels:write,channels:read,groups:read,groups:write,incoming-webhook,commands,files:read,links:read,channels:history,chat:write:user,chat:write:bot';
	$slackUrl .= '&redirect_uri=' . $this->slackRedirectUrl;
?>
<a href="<?php echo $slackUrl;?>" data-nonceUrl="<?php echo $this->nonce;?>">
	<img alt="Add to Slack" height="40" width="139" 
		src="https://platform.slack-edge.com/img/add_to_slack.png" 
		srcset="https://platform.slack-edge.com/img/add_to_slack.png 1x, https://platform.slack-edge.com/img/add_to_slack@2x.png 2x" 
	/>
</a>
<?php
	$this->js('slack');
?>
