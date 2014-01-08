jQuery(function($){
	var now = new Date();
	now.setUTCSeconds(0);
	now.setUTCMinutes(0);
	now.setUTCHours(0);
	
	monthNames = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];

	// firstsecond in month
	currentMonth = Math.floor(new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), 1, 0, 0, 0)) / 1000);
	
	console.log( 'My Now: ' + Math.floor(now / 1000));
	console.log('Current Month: ' + currentMonth);
	nowUnix = Math.floor(now / 1000);
	console.log( 'Unix Now: '+ nowUnix);
	
	// show only current month, initially
	initialMonth();
	function initialMonth($kill_get){
		switchMonth(currentMonth);
		$('td.calendar-day[data-dates="' + nowUnix + '"]').addClass('todays-date');
		var $get_cat = getParameterByName('event_cat');
		
		if($get_cat == 0 || $kill_get == 'kill_get'){
			$('div.event').removeClass('event-select-hider');
		} else {
			$('select[name=event-category]').val($get_cat);
			var $selected_text = $($get_cat).filter(':selected').text();
		
			$('div.event').addClass('event-select-hider');
			$('div.event[data-terms*="' + $get_cat + '"]').removeClass('event-select-hider');
			printFilterResults($selected_text, 'drop');
		}
		makePagination();
	}
	
	// month
	$('a.next-month, a.prev-month, a.view-month').on('click', function(){
		var $chosenMonth = $(this).data('month');
		switchMonth($chosenMonth);
		makePagination();
	});
	function switchMonth($chosenMonth){
		console.log($chosenMonth);
		$('table.calendar').hide();
		$('table.calendar[data-month="' + $chosenMonth + '"]').show();
		$('td.calendar-day').removeClass('chosen-day');
		$('div.event').addClass('grid-hider');
		
		var $visibleEvents = $('div.event').filter(function(){
			var d = new Date($chosenMonth * 1000);
			var monthEnd = Math.floor( new Date(d.getUTCFullYear(), d.getUTCMonth() +1, 0, 23, 59, 59) / 1000 );
			for (var $dates = $(this).data('dates'), i = $dates.length; i--;){
				if($dates[i] >= $chosenMonth && $dates[i] <= monthEnd) {
					return true;
				}
			}
			return false;
		});
		
		$visibleEvents.removeClass('grid-hider');
		printFilterResults($chosenMonth, 'month');
	}
	
	// week
	$('td.week-selector a').on('click', function(){
		var $chosenWeek = $(this).parent().parent().parent().children('.calendar-day');
		$('tr.calendar-row td').removeClass('chosen-day');
		$chosenWeek.addClass('chosen-day');
		
		$('div.event').addClass('grid-hider');
		var $visibleEvents = $('');
		$chosenWeek.each(function(){
			$visibleEvents = $visibleEvents.add($('div.event[data-dates*="' + $(this).data('dates') + '"]'));
		});
		$visibleEvents.removeClass('grid-hider');
		
		$week_of = $chosenWeek.filter(function(){
			return $(this).data('dates') !== undefined;
		}).first().data('dates');
		
		printFilterResults($week_of, 'week');
		makePagination();
	});
	
	// day
	$('div.day-number a').on('click', function(){
		var $chosenDay = $(this).data('dates');
		switchDay($chosenDay);
		makePagination();
	});
	function switchDay($chosenDay){
		$('div.event').addClass('grid-hider');
		$('td.calendar-day').removeClass('chosen-day');
		$('td.calendar-day[data-dates="' + $chosenDay + '"]').addClass('chosen-day');
		
		var $visibleEvents = $('div.event[data-dates*="' + $chosenDay + '"]');
		$visibleEvents.removeClass('grid-hider');
		printFilterResults($chosenDay, 'day');
	}
	
	// taxonomy term chooser
	$('div.event-taxonomy-box select').change(function(){
		var $selected_slug = $(this).val();
		var $selected_text = $(this).children('option').filter(':selected').text();
		
		if($selected_slug == '*'){
			$('div.event').removeClass('event-select-hider');
		} else {
			$('div.event').addClass('event-select-hider');
			$('div.event[data-terms*="' + $selected_slug + '"]').removeClass('event-select-hider');
		}
		printFilterResults($selected_text, 'drop');
		makePagination();
	});
	
	// STRICT SEARCH
    $("#text_search").keyup(function () {
        var filter = $(this).val();
    
        $("div.event").each(function () {
            var length = $(this).text().length>0;
    
            if ( length && $(this).text().search(new RegExp(filter, "i")) < 0) {
                $(this).addClass('text-hider');
            } else {
                $(this).removeClass('text-hider');
        	}
        });
        printFilterResults(filter, 'text');
        makePagination();
    });
    
	// reset the form, show all
	$("#reset").click(function() {
		$(':input', '#event_search').not(':button, :submit, :reset, :hidden').val('').removeAttr('selected');
		$('.event').removeClass('text-hider event-select-hider');
		initialMonth('kill_get');
		printFilterResults('', 'reset');
	});
	
	
	// prevent RETURN from submiting (useful for text searches)
	$('#event_search').keypress(function(event){
		if(event.keyCode == 13 || event.keyCode == 10) {
			event.preventDefault();
			return false;
		}
	});
	
	function makePagination(){
		$('div.event').removeClass('page-hider');
		var pagedItems = $('div.event:visible');
		var numPagedItems = pagedItems.length;
		var itemsPerPage = 5;
		var numPages = Math.ceil(numPagedItems / itemsPerPage);
		var noEvents = $('.no-events');
		
		if(numPagedItems === 0){
			noEvents.show();
		}
		
		if(numPagedItems > 0){
			noEvents.hide();
		}
		
		if(numPagedItems < 6){
			$('ul.bootpag').remove();
			return;
		}
		
		var i = 0;
		var z = 1;
		pagedItems.each(function(){
			if(i !== 0 && i % itemsPerPage === 0){
				z = z + 1;
			}
			$(this).attr('data-page', z);    
			i++;                        			
		});
		                          			
		$('.pagination').bootpag({
			total: numPages,
			maxVisible: numPages,
			page: 1,
		}).on("page", function(event, num){
			$("div.event").addClass('page-hider');
			$('div.event[data-page="' + num + '"]').removeClass('page-hider');
		});
		$('div.event').addClass('page-hider');
		$('div.event[data-page="' + 1 + '"]').removeClass('page-hider');
	}
	
	function printFilterResults(result, type){
		var time_result = $('h2.calendar-result-announcement span.time-result-announcement');
		var text_result = $('h2.calendar-result-announcement span.text-result-announcement');
		var drop_result = $('h2.calendar-result-announcement span.drop-result-announcement');
		switch(type){
			case 'month':
				var resultDay = new Date(1000 * (result + 3600));
				result_string = 'Month of ' + monthNames[resultDay.getUTCMonth()] + ' ' + resultDay.getUTCFullYear();
				time_result.html(result_string);
				break;
				
			case 'week':
				var resultDay = new Date(1000 * (result + 3600));
				result_string = 'Week of ' + monthNames[resultDay.getUTCMonth()] + ' ' + resultDay.getUTCDate() + daySuffix(resultDay.getUTCDate());
				time_result.html(result_string);
				break;
				
			case 'day':
				var resultDay = new Date(1000 * (result + 3600));
				result_string = 'Day of ' + monthNames[resultDay.getUTCMonth()] + ' ' + resultDay.getUTCDate() + daySuffix(resultDay.getUTCDate());
				time_result.html(result_string);
				break;
				
			case 'text':
				if(result.length == 0){
					text_result.html('');
					return;
				}
				if(time_result.length > 0){
					var result_string = ', ';
				} else {
					var result_string = '';
				}
				result_string = result_string + '"' + result + '"';
				text_result.html(result_string);
				break;
			
			case 'drop':
				if(result.length == 0){
					drop_result.html('');
					return;
				}
				if(drop_result.length > 0){
					var result_string = ', ';
				} else {
					var result_string = '';
				}
				result_string = result_string + '"' + result + '"';
				drop_result.html(result_string);
				break;
				
			case 'reset':	
				text_result.html('');
				drop_result.html('');
				break;
				
			default:
				return;
				break;
		}
		
	}
	
	function daySuffix(d) {
	    d = String(d);
	    return d.substr(-(Math.min(d.length, 2))) > 3 && d.substr(-(Math.min(d.length, 2))) < 21 ? "th" : ["th", "st", "nd", "rd", "th"][Math.min(Number(d)%10, 4)];
	}
	
	function getParameterByName(name) {
	    name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
	    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
	        results = regex.exec(location.search);
	    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
	}
});