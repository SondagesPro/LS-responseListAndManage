$(window).scroll(function(){
    $('#responses-grid > .row-fluid').css({
        'left': $(this).scrollLeft()
    });
});
$(window).on('resize', function(){
  updateHeightModalbody("#modal-survey-update");
  $("#responses-grid").css("padding-bottom",$("#responses-grid > .row").height()+"px");
});
$(function() {
  $('#responses-grid > .row-fluid').css({
        'left': $(window).scrollLeft()
  });
  $("#responses-grid").css("padding-bottom",$("#responses-grid > .row").height()+"px");
});
$(document).on("ajaxUpdated","#responses-grid",function(event){
    $(this).css('padding-bottom',$(this).children('.row').height()+'px');
});
$(document).on("click","a.update",function(event){
    event.preventDefault();
    $("iframe#survey-update").attr("src",$(this).attr("href"));
    $("#modal-survey-update").modal('show');
    updateHeightModalbody("#modal-survey-update");
});

$(document).on("click","a.addnew",function(event){
    event.preventDefault();
    $("iframe#survey-update").attr("src",$(this).attr("href"));
    $("#modal-survey-update").modal('show');
    updateHeightModalbody("#modal-survey-update");
});

$(document).on("click","button.addnew",function(event){
    event.preventDefault();
    if(!$("#token").val()) {
        $("#token").focus()
        // TODO : show a warning error
        return;
    }
    var href = $(this).closest('form').attr('action');
    href += (href.indexOf('?') > -1) ? '&' : '?';
    href += 'sid='+$("#sid").val();
    href += '&newtest=Y';
    href += '&token='+$("#token").val();
    href += '&srid='+$(this).val();
    $("iframe#survey-update").attr("src",href);
    $("#modal-survey-update").modal('show');
    updateHeightModalbody("#modal-survey-update");
});

$(document).on("click","[name='adduser']",function(event){
    event.preventDefault();
    $("#modal-create-token").modal('show');
    //~ updateHeightModalbody("#modal-survey-update");
});

function updateHeightModalbody(modal)
{
    var navbarFixed=0;
    if(false && (".navbar-fixed-top").length) {
      navbarFixed=$(".navbar-fixed-top").outerHeight();
    }
    var modalHeader=$(modal).find(".modal-header").outerHeight();
    var modalFooter=$(modal).find(".modal-footer").outerHeight();
    var finalHeight=Math.max(150,$(window).height()-(navbarFixed+modalHeader+modalFooter+28));// Not less than 150px
    $(modal).find(".modal-lg").css("margin-top",navbarFixed);
    $(modal).find(".modal-body").css("height",finalHeight);
    $(modal).find(".modal-body iframe").css("height",finalHeight);
}
$(document).on("shown.bs.modal","#modal-survey-update",function(e) {
    updateHeightModalbody("#modal-survey-update");
});
$(document).on("hide.bs.modal","#modal-survey-update",function(e) {
    $.fn.yiiGridView.update('responses-grid');
    $("#survey-update").attr('src',"");
});

$(document).on('surveyiniframe:on',function(event,data) {
  $("#modal-survey-update .modal-footer button[data-action]").each(function(){
    //~ $(this).prop('disabled',true);
    $(this).prop('disabled',$("#survey-update").contents().find("form#limesurvey button:submit[value='"+$(this).data('action')+"']").length < 1);
    if($("#survey-update").contents().find(".completed-text").length) {
        $("#modal-survey-update").modal('hide');
    }
  });
});
$(document).on('surveyiniframe:off',function(event,data) {
  $("#modal-survey-update .modal-footer button[data-action]").each(function(){
    $(this).prop('disabled',true);
  });
});

$(document).on('surveyiniframe:autoclose',function(event,data) {
  $("#modal-survey-update").modal('hide');
});

$(document).on('click',"button[data-action]:not('disabled')",function(e) {
    //console.log($(this).data($("#survey-update").contents().find("button:submit[value='"+$(this).data('action')+"']").length));
    //$("#survey-update").contents().find("form#limesurvey button:submit[value='"+$(this).data('action')+"']").last().click();
    //~ if($(this).data('action')=='saveall') {
        //~ //$("#survey-update").contents().find("form#limesurvey button:submit[value='"+$(this).data('action')+"']").last().click();
        //~ var submit=$(this).data('limesurvey-submit');
        //~ return;
    //~ }
    $("#survey-update").contents().find("form#limesurvey button:submit[value='"+$(this).data('action')+"']").last().click();
});
