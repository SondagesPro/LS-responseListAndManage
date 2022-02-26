/**
 * @todo : move to js class
 */

$(window).scroll(function(){
    $('#responses-grid > .row-fluid').css({
        'left': $(this).scrollLeft()
    });
});

$(window).on('resize', function(){
  updateHeightModalbody("#modal-responseListAndManage");
  $("#responses-grid").css("padding-bottom",$("#responses-grid > .row").height()+"px");
});

$(function() {
  $('#responses-grid > .row-fluid').css({
        'left': $(window).scrollLeft()
  });
  if($(".navbar-fixed-top").length && (".responselistandmanage-sticky-header").length) {
      $(".responselistandmanage-sticky-header #responses-grid thead").css('top',$(".navbar-fixed-top").height() + "px");
  }
  $("body").find('.outerframe.container').removeClass('container').addClass('container-fluid'); // Needed for 2.5X 
  $("#responses-grid").trigger('ajaxUpdated');
});

$(document).on("ajaxUpdated","#responses-grid",function(event){
    $(this).css('padding-bottom',$(this).children('.row').height()+'px');
    $('#responses-grid thead small[title]').tooltip({
        html:true,
        placement:'bottom',
        viewport: function() {
            return this.$element[0];
        }
    });
    $('#responses-grid .answer-value,#responses-grid .tokenattribute-value').popover({
        html : true,
        sanitize : false,
        placement : 'bottom',
        viewport: function() {
            return this.$element[0];
        },
        content: function() {
            return $(this).html().trim();
        },
        title : function() {
            return null;
        }
    });
    $('#responses-grid .filter-date').each(function() {
        var dateFormat = $(this).data('format')? $(this).data('format') : 'YYYY-MM-DD';
        $(this).datetimepicker({
            format : dateFormat,
            icons : {"time":"fa fa-clock-o","date":"fa fa-calendar","up":"fa fa-chevron-up","down":"fa fa-chevron-down","previous":"fa fa-chevron-left","next":"fa fa-chevron-right","today":"fa fa-calendar-check-o","clear":"fa fa-trash-o","close":"fa fa-closee"},
            allowInputToggle : true,
            showClear : true,
            sideBySide : true
        });
    });

    $('#responses-grid .filter-date').on('dp.hide', function(){
        $('#responses-grid .filters input:not(.filter-date)').first().trigger('change'); /* Simulate a chang on first input, : change on self seems disable by datetimepicker */
    });
});

$(document).on('show.bs.popover','#responses-grid .answer-value', function () {
    $('#responses-grid .answer-value').not(this).popover('hide');
});

$(document).on("click","a.update[href]",function(event){
    event.preventDefault();
    $('#responses-grid .answer-value').popover('hide');
    $("iframe#frame-responseListAndManage").attr("src",$(this).attr("href"));
    $("#modal-responseListAndManage").modal('show');
    updateHeightModalbody("#modal-responseListAndManage");
});

$(document).on("click","a.addnew",function(event){
    event.preventDefault();
    $('#responses-grid .answer-value').popover('hide');
    $("iframe#frame-responseListAndManage").attr("src",$(this).attr("href"));
    $("#modal-responseListAndManage").modal('show');
    updateHeightModalbody("#modal-responseListAndManage");
});

$(document).on("click","button.addnew",function(event){
    event.preventDefault();
    $('#responses-grid .answer-value').popover('hide');
    if(!$("#token").val()) {
        $("#token").focus();
        // TODO : move to validate
        return;
    }
    var urlParams =  $(this).closest('form').serialize();
    var action =  $(this).closest('form').attr('action');
    if(action.indexOf("?")) {
        var href = $(this).closest('form').attr('action')+"&"+urlParams;
    } else{
        var href = $(this).closest('form').attr('action')+"?"+urlParams;
    }

    $("iframe#frame-responseListAndManage").attr("src",href);
    $("#modal-responseListAndManage").modal('show');
    updateHeightModalbody("#modal-responseListAndManage");
});

$(document).on("click","[name='adduser']",function(event){
    event.preventDefault();
    $('#responses-grid .answer-value').popover('hide');
    $(".wysihtml5-toolbar .btn").each(function(){ /* bad hack */
        $(this).addClass("btn-default btn-xs");
    });
    $(".wysihtml5-toolbar .icon-pencil").addClass("fa fa-edit");
    $("#modal-create-token").modal('show');
});

$(document).on("click","#modal-create-token button:submit",function(event,data){
    data = $.extend({source:null}, data);
    $('#responses-grid .answer-value').popover('hide');
    if(data.source == 'control') {
        return;
    }
    event.preventDefault();
    var $form = $("#modal-create-token form")[0];
    if (!$form.checkValidity()) {
        $('#modal-create-token button:submit').trigger('click',{source:'control'});
        return;
    }
    var url = $("#modal-create-token form").attr("action");
    var params = $("#modal-create-token form").serializeArray();
    $.ajax({
       url : url,
       type : 'GET',
       data : params,
       dataType : 'json'
    })
    .done(function(data) {
        data = $.extend({}, {status:'warning'}, data);
        if(data.status == "success") {
            $.fn.yiiGridView.update('responses-grid');
            $("#modal-create-token").modal('hide');
            return;
        }
        if(data.html) {
            var className = data.status;
            if(data.status == 'error') {
                className = 'danger';
            }
            var htmlAlert = "<div class='alert alert-"+className+"'>"+data.html+"</div>";
            $("#create-token-errors").html(htmlAlert);
            $('#modal-create-token').animate({
                scrollTop: $("#create-token-errors").position().top
            }, 500);
            return;
        }
    })
    .fail(function( jqXHR, textStatus ) {
        //console.log([jqXHR,textStatus]);
        $("#create-token-errors").html(jqXHR.responseText);
    });
    
});
function updateHeightModalbody(modal)
{
    var marginTopModal = 0;
    var marginBottomModal = 0;
    if($(".rlm-modal-margin-top").length) {
      marginTopModal = $(".rlm-modal-margin-top").outerHeight();
    }
    if($(".rlm-modal-margin-bottom").length) {
      marginBottomModal = $(".rlm-modal-margin-bottom").outerHeight();
    }
    var modalHeader=$(modal).find(".modal-header").outerHeight();
    var modalFooter=$(modal).find(".modal-footer").outerHeight();
    var finalHeight=Math.max(150,$(window).height()-(marginTopModal+modalHeader+modalFooter+marginBottomModal+28));// Not less than 150px
    $(modal).find(".modal-lg").css("margin-top",marginTopModal);
    $(modal).find(".modal-body").css("height",finalHeight);
    $(modal).find(".modal-body iframe").css("height",finalHeight);
}
$(document).on("shown.bs.modal","#modal-responseListAndManage",function(e) {
    updateHeightModalbody("#modal-responseListAndManage");
});
$(document).on("hide.bs.modal","#modal-responseListAndManage",function(e) {
    $.fn.yiiGridView.update('responses-grid');
    $("#frame-responseListAndManage").attr('src',"");
});

$(document).on("hide.bs.modal","#modal-create-token",function(e) {
    $("#modal-create-token form").find("input[type='email'],input:text,textarea").each(function(){
        $(this).val("");
        if($(this).data("default")) {
            $(this).val($(this).data("default"));
        }
    });
    if($("#emailbody").data("wysihtml5")) {
        $("#emailbody").data("wysihtml5").editor.setValue($("#emailbody").data("default"));
    }
    $("#create-token-errors").html("");
});

$(document).on('surveyiniframe:on',function(event,data) {
  $("#modal-responseListAndManage .modal-footer button[data-action]").each(function(){
    $(this).prop('disabled',$("#frame-responseListAndManage").contents().find("form#limesurvey button:submit[value='"+$(this).data('action')+"']").length < 1);
  });
  $("#modal-responseListAndManage .modal-footer button[data-action-extra='saveallquit']").prop(
    'disabled',
    $("#frame-responseListAndManage").contents().find("form#limesurvey button:submit[value='saveall']").length < 1
  );
  $("#modal-responseListAndManage .modal-footer button[data-action-extra='delete']").prop(
    'disabled',
    $("#frame-responseListAndManage").contents().find("form#limesurvey button:submit[value='clearall']").length < 1
  );
});
$(document).on('surveyiniframe:off',function(event,data) {
  $("#modal-responseListAndManage .modal-footer button[data-action], #modal-responseListAndManage .modal-footer button[data-action-extra]").each(function(){
    $(this).prop('disabled',true);
  });
});

$(document).on('surveyiniframe:autoclose',function(event,data) {
  $("#modal-responseListAndManage").modal('hide');
});

$(document).on('click',"button[data-action]:not('disabled')",function(e) {
    $('#responses-grid .answer-value').popover('hide');
    $("#frame-responseListAndManage").contents().find("form#limesurvey button:submit[value='"+$(this).data('action')+"']").last().click();
});
$(document).on('click',"button[data-action-extra='saveallquit']:not('disabled')",function(e) {
  $("#frame-responseListAndManage").contents().find("form#limesurvey").append("<input type='hidden' name='autosaveandquit' value=1>");
  $("#frame-responseListAndManage").contents().find("form#limesurvey button:submit[value='saveall']").last().click();
});
$(document).on('click',"button[data-action-extra='delete']:not('disabled')",function(e) {
  $("#frame-responseListAndManage").contents().find("form#limesurvey button:submit[value='clearall']").last().click();
});

function activateExport(){
    $(document).on('click',"[data-export-checked]",function(e) {
        e.preventDefault();
        $("#link-clone").remove();
        var checked = $('#responses-grid').yiiGridView('getSelection').toString();
        var newUrl = $(this).attr("href");
        newUrl = newUrl+"&checkeds="+checked; // Quick way : send it in a string …
        var newLink = $(this).clone().attr("id","link-clone").addClass("hidden").removeAttr("data-export-checked").attr("href",newUrl);
        $(newLink).appendTo("body");
        document.getElementById("link-clone").click(); // Then we keep the download=1
    });
}
activateExport();
/* html(parser rules */
var wysihtml5ParserRules = {
  tags: {
    strong: {},
    b:      {
        rename_tag: "strong"
    },
    em:     {},
    i:      {
        rename_tag: "em"
    },
    br:     {},
    p:      {},
    div:    {},
    span:   {},
    ul:     {},
    ol:     {},
    li:     {},
    a:      {
      set_attributes: {
        target: "_blank"
      },
      check_attributes: {
        href:   "url"
      }
    },
    img: {
        check_attributes: {
            src: "url",
            width : "numbers",
            alt : "alt",
            height : "numbers"
        },
    },
    link: {
        remove: 1
    },
    script: {
        remove: 1
    }
  }
};
