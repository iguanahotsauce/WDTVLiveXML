$(document).ready(function() {
	$('.btn').button()
    $("#show_name").focus();
    $("#show_name").autocomplete({
        minLength: 0,
        delay:5,
        source: "./apps/imdbAutoComplete.php",
        select: function(event, ui) {
            $(this).val(ui.item.label);
            if(ui.item.q == 'feature') {
	            $("#type").val(1);
            }
            else if(ui.item.q == 'TV series') {
	            $("#type").val(19);
            }
            else {
	            $("#type").val(null);
            }
            return false;
        }
    }).data("uiAutocomplete")._renderItem = function(ul, item) {
        return $("<li></li>")
            .data( "item.autocomplete", item )
            .append("<a><span class='imdbTitle'>" + item.label + "</span>" + (item.cast?"<br /><span class='imdbCast'>" + item.cast + "</span>":"") + "<div class='clear'></div></a>")
            .appendTo(ul);
    }
    $('.submit-button').click(function() {
    	$('body').addClass('cursor-wait');
    	var selected_button = null;
    	$('.btn').children().each(function() {
    		if($(this).parent().hasClass('active')) {
		    	selected_button = $(this).attr('value');
		    }
	    });
	    $.ajax({
		    type: 'POST',
		    url: '../apps/getShowInfo.php',
		    data: { name: $('#show_name').val(), type: $('#type').val(), selected_button: selected_button}
	    }).done(function(data) {
	    	data = jQuery.parseJSON(data);
		    if(data.seasons != 0) {
		    	var container = $('<div id="season_list"></div>');
			    for(var i=1;i<=data.seasons;i++) {
				    container.append($('<div class="season_number item" id="season_'+i+'" value="'+data.urlencode+i+'"><span class="name">'+data.name+' Season '+i+'</span></div>'));
			    }
		    }
		    else {
		    	var container = $('<div id="movie_container"></div>');
			    container.append($('<div class="movie item" id="season_0" value="'+data.urlencode+i+'"><span class="name">'+data.name+'</span></div>'));
		    }
		    $('#seasons').empty();
		    $('#seasons').append(container);
		    $('body').removeClass('cursor-wait');
            $('.item').children('.name').click(function() {
            	if($(this).parent().hasClass('loaded')) {
	            	$(this).parent().children('.results').toggle();
            	}
            	else {
            		$('body').addClass('cursor-wait');
            		$(this).parent().addClass('loaded');
	                $.ajax({
	                    type: 'POST',
	                    url: './apps/getImages.php',
	                    data: {getData: 'true', type: $('#type').val(), season_number: $(this).parent().attr('id'), start_name: $(this).html()}
	                }).done(function(data) {
	                	data = jQuery.parseJSON(data);
						var element = $('<div class="results"> \
		                    	<div class="title"> \
									<h5>Title: </h5> \
									<span>'+data.key+'</span> \
								</div> \
								<div class="percent"> \
									<h5>Percent Match: </h5> \
									<span>'+data.percent+'</span> \
								</div> \
								<div class="image"> \
									<div id="original_image"> \
										<h3>Original Image</h3> \
										<img src="'+data.url+'"> \
									</div> \
									<div id="cropped_image"> \
										<h3>Cropped Image</h3> \
										<img src="'+data.image+'"> \
									</div> \
								</div> \
							</div>');
	                    $('#season_'+data.season_number).append(element);
	                    $('body').removeClass('cursor-wait');
	                });
                }
            });
	    });
    });
});