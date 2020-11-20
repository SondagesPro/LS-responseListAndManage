$(document).on('ready pjax:scriptcomplete',function(){
    if(window.location != window.parent.location) {
        window.parent.$(window.parent.document).trigger("surveyiniframe:on");
    }
    $("form#limesurvey").append("<input type='hidden' id='plugin' name='plugin' value='responseListAndManage'>");
});
window.onbeforeunload = function() {
    if(window.location != window.parent.location) {
        window.parent.$(window.parent.document).trigger("surveyiniframe:off");
    }
};
var responseListAndManage = {
    timeOut : 1,
    autoclose : function () {
        window.setTimeout(function(){
            window.parent.$(window.parent.document).trigger("surveyiniframe:autoclose");
        }, responseListAndManage.timeOut * 1000);
    }
}
