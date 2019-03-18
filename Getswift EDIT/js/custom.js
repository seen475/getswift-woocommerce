
	jQuery(document).ready(function() {	
		jQuery('#swift_dropoffearly').datetimepicker({
			controlType: 'select',
			timeFormat: "HH:mm",
			minDate: 0			
		});
		jQuery('#swift_dropofflatest').datetimepicker({
			controlType: 'select',
			timeFormat: 'HH:mm',
			minDate: 0		
		});
	});
