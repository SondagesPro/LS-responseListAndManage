//~ if(window.location != window.parent.location) {
    //~ window.parent.$(window.parent.document).trigger("initiativeautoclosed:on", {url:window.location.href});
//~ }
//~ $(document).on('ready pjax:scriptcomplete',function(){
    //~ if(window.location != window.parent.location) {
        //~ window.parent.$(window.parent.document).trigger("modaliniframe:on");
    //~ }
//~ }
$(document).on('ready pjax:scriptcomplete',function(){
    if(window.location != window.parent.location) {
        window.parent.$(window.parent.document).trigger("surveyiniframe:on");
    }
});
window.onbeforeunload = function() {
    if(window.location != window.parent.location) {
        window.parent.$(window.parent.document).trigger("surveyiniframe:off");
    }
};
function autoclose() {
    if(window.location != window.parent.location) {
        window.parent.$(window.parent.document).trigger("surveyiniframe:autoclose");
    }
}
