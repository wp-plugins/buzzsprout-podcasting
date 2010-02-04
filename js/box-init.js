jQuery(function($){
    /*
    Can't found a native API for adding a custom icon into media panel
    hence this work around using jQuery.
    
    We dynamically create an <a> element and append it into the panel
    with proper ID, href, title, and wrap it around a cool icon.
    
    Then, remember to call tb_init to enable ThickBox on the link.
    */
    var $button = $('<a/>')
        .attr("id", "buzzpButton")
        .attr("href", "index.php?buzzp_action=box&TB_iframe=true&width=640&height=200")
        .attr("title", "Buzzsprout Podcasting")
        .html('<img src="../wp-content/plugins/buzzsprout-podcasting/images/button.png" alt="Buzzsprout Podcasting" />')
        .click(function(e){
        e.preventDefault();
    });
    
    $("#media-buttons").append($button);
    tb_init("#buzzpButton");
});

function buzzpPickHandler(linkElement)
{       
    send_to_editor(jQuery(linkElement).attr("short-tag")); 
}