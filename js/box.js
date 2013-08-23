jQuery(function($){   
    $("a.buzzp-item").click(function(e){
        e.preventDefault();
        parent.buzzsproutPickHandler(this);
    });
});