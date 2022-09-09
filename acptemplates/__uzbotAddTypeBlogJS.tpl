$('.blog_blogChange, .blog_entryChange, .blog_entryCount, .blog_blogNew').hide();
$('.blog_entryNew, .blog_statistics, .blog_topBlogger').hide();

if (value == 50) {
	$('.blog_blogNew, .uzbotUserConditions').show();
	$('#receiverAffected').show();
}

if (value == 51) {
	$('.blog_entryNew, .uzbotUserConditions').show();
	$('#receiverAffected').show();
}

if (value == 52) {
	$('.blog_blogChange, .uzbotUserConditions').show();
	$('#receiverAffected').show();
}

if (value == 53) {
	$('.blog_statistics').show();
}

if (value == 54) {
	$('.blog_entryChange, .uzbotUserConditions').show();
	$('#receiverAffected').show();
}

if (value == 55) {
	$('.blog_entryCount, .uzbotUserConditions, .user_count').show();
	$('#receiverAffected').show();
}

if (value == 56) {
	$('.blog_topBlogger, .condenseSetting, .uzbotUserConditions').show();
	$('#receiverAffected').show();
	if ($('#condenseEnable').is(':checked')) { $('.notifyCondense').show(); }
}
