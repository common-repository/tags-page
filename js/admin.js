jQuery(document).ready(function($){

  // URL dialog code:
  
  var instance;
  
  $('body').on('click', '.tags-page-config input.button', function(event) {
    instance = $(this).attr("for");
    window.wpActiveEditor = true; //we need to override this var as the link dialogue is expecting an actual wp_editor instance
    wpLink.open(); //open the link popup
    return false;
  });
  
  $('body').on('click', '#wp-link-submit', function(event) {
    var linkAtts = wpLink.getAttrs();//the links attributes (href, target) are stored in an object, which can be access via  wpLink.getAttrs()
    $("#" + instance).val(linkAtts.href);//get the href attribute and add to a textfield, or use as you see fit
    wpLink.textarea = $('body'); //to close the link dialogue, it is again expecting an wp_editor instance, so you need to give it something to set focus back to. In this case, I'm using body, but the textfield with the URL would be fine
    wpLink.close();//close the dialogue
    //trap any events
    event.preventDefault ? event.preventDefault() : event.returnValue = false;
    event.stopPropagation();
    return false;
  });
  
  $('body').on('click', '#wp-link-cancel, #wp-link-close', function(event) {
    wpLink.textarea = $('body');
    wpLink.close();
    event.preventDefault ? event.preventDefault() : event.returnValue = false;
    event.stopPropagation();
    return false;
  });

  // show/hide based on value of taxonomy attribute:

  $('body').on('change', 'select.widefat', function(event) {
    var id = $(this).attr("id");
    var div = '#' + id.substring(0, id.length - 8) + 'tags_page_config';
    if($(this).val() == "post_tag")
      $(div).slideDown(150);
    else
      $(div).slideUp(150);
  });
});