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
    autoclose : function () {
        if(window.location != window.parent.location) {
            window.parent.$(window.parent.document).trigger("surveyiniframe:autoclose");
        }
    }
}
