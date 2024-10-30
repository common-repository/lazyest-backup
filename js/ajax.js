/* ajax zip file */  
function lazyestBackupNextZip( toGo, toDo ) {
  var data = {
    action: 'lazyest_zip_folder',
    folder: toGo
  }
  jQuery.post( ajaxurl, data, function( response ) { 
    toGo = parseInt( response, 10 );
    percentage = 100 - Math.floor( 100 * toGo / toDo );
    jQuery('#zipper-bar').progressBar( percentage );
    if ( 0 < toGo ) {
      lazyestBackupNextZip( toGo, toDo );
    } else {
    	if ( 0 == toGo ) {
        jQuery('#backup_busy').html( lazyest_backup.Ready );
	      var data = {
	      	action: 'lazyest_backup_notice',
	      	process: 'created',
	      	result: 'true',
	      }
	      jQuery.post( ajaxurl, data, function(response){
	      	jQuery(window).unbind('beforeunload');
	      	jQuery(location).attr('href','admin.php?page=lazyest-backup');	
	      });	      
			} else {
        jQuery('#zipper-bar').progressBar( 0 );
				var data = {
	      	action: 'lazyest_backup_notice',
	      	process: 'created',
	      	result: 'false',
	      }				
	      jQuery.post( ajaxurl, data, function(){
	      	jQuery(window).unbind('beforeunload');
	      	jQuery(location).attr('href','admin.php?page=lazyest-backup');	
	      });	          				
			}
    }        
  });
}

var lazyestBackupUnzipped = 0;
function lazyestBackupNextRestore( toGo, toDo ) {
	var data = {
    action: 'lazyest_restore_hundred',
    hundred: toGo
  }
  jQuery.post( ajaxurl, data, function( response ) {
  	Value = response.split(',');
    toGo = parseInt( Value[0], 10 );
    lazyestBackupUnzipped = lazyestBackupUnzipped + parseInt( Value[1], 10 ); 
    percentage = 100 - Math.floor( 100 * toGo / toDo );
    jQuery('#zipper-bar').progressBar( percentage );  
    if ( 0 < toGo ) {
      lazyestBackupNextRestore( toGo, toDo );
    } else {
    	if ( 0 == toGo ) {
        jQuery('#backup_busy').html( lazyest_backup.Ready );
	      var data = {
	      	action: 'lazyest_backup_notice',
	      	process: 'restored',
	      	result: lazyestBackupUnzipped,
	      }
	      jQuery.post( ajaxurl, data, function(){
	      	jQuery(window).unbind('beforeunload');
	      	jQuery(location).attr('href','admin.php?page=lazyest-backup');	
	      });	
			} else {
        jQuery('#zipper-bar').progressBar( 0 );
				var data = {
	      	action: 'lazyest_backup_notice',
	      	process: 'restored',
	      	result: '-3',
	      }
	      jQuery.post( ajaxurl, data, function(){
	      	jQuery(window).unbind('beforeunload');
	      	jQuery(location).attr('href','admin.php?page=lazyest-backup');	
	      });    				
			}
    }        
  });
}

jQuery(document).ready( function() {
	
	jQuery('#zipper-bar').progressBar( { boxImage: lazyest_backup.boxImage, barImage: lazyest_backup.barImage } );
	
  jQuery('#create-archive').click( function() {
  	jQuery('input[type=submit], input[type=file]').attr('disabled', 'disabled');
  	jQuery('input[type=image]').hide();
  	jQuery('#download-current').css( 'visibility', 'hidden' );  	
   	jQuery('#backup_busy').html( lazyest_backup.Analyzing );
    jQuery('#zipper-bar').css('visibility', 'visible' );
    var data = {
    	action: 'lazyest_zip_folder',
    	folder: 0
    }
    jQuery(window).bind('beforeunload', function() {
			return lazyest_backup.Archiving;
		});
    jQuery.post( ajaxurl, data, function( response ) {
   		var toGo = parseInt( response, 10 );   
      var toDo = parseInt( response, 10 );         
      if ( 0 < toDo ) {var percentage = 100 - Math.floor( 100 * ( toGo / toDo ) );
        jQuery('#zipper-bar').progressBar( percentage );
        jQuery('#backup_busy').html( lazyest_backup.busyArchiving );
        lazyestBackupNextZip( toGo, toDo );
      } else {
        jQuery('#zipper-bar').progressBar( 0 );	var data = {
	      	action: 'lazyest_backup_notice',
	      	process: 'created',
	      	result: 'false',
	      }				
	      jQuery.post( ajaxurl, data, function(){
	      	jQuery(window).unbind('beforeunload');
	      	jQuery(location).attr('href','admin.php?page=lazyest-backup');	
	      });	        
      }        
   	});    
    return false;
  });
  
  jQuery('#restore-archive').click( function() {
  	jQuery('input[type=submit], input[type=file]').attr('disabled', 'disabled');
  	jQuery('input[type=image]').hide();
  	jQuery('#download-current').css( 'visibility', 'hidden' );  	
   	jQuery('#backup_busy').html( lazyest_backup.Analyzing );
    jQuery('#zipper-bar').css('visibility', 'visible' );
    var data = {
    	action: 'lazyest_restore_hundred',
    	hundred: 0
    }
    jQuery(window).bind('beforeunload', function() {
			return lazyest_backup.Restoring;
		});
    jQuery.post( ajaxurl, data, function( response ) {
  		Value = response.split(',');
    	var toGo = parseInt( Value[0], 10 );    	
    	var toDo = parseInt( Value[0], 10 );        
      if ( 0 < toDo ) {
      	var percentage = 100 - Math.floor( 100 * ( toGo / toDo ) );
        jQuery('#zipper-bar').progressBar( percentage );
        jQuery('#backup_busy').html( lazyest_backup.busyRestoring );
        lazyestBackupNextRestore( toGo, toDo );
      } else {
        jQuery('#zipper-bar').progressBar( 0 );	
				var data = {
	      	action: 'lazyest_backup_notice',
	      	process: 'restored',
	      	result: '-2',
	      }				
	      jQuery.post( ajaxurl, data, function(){
	      	jQuery(window).unbind('beforeunload');
	      	jQuery(location).attr('href','admin.php?page=lazyest-backup');	
	      });	  			       
      }        
   	});    
    return false;
  });
  
  jQuery('#zip_upload').change(function(){  	
  	zipFile = jQuery(this).val();
  	if ( zipFile.indexOf('.zip') ) {
  		jQuery('#upload_zip').removeAttr('disabled');
  	}
  });
  
  jQuery('input[name=xml_or_all]').change(function(){
  	var data = {
  		action: 'lazyest_pattern',
  		xml_or_all: jQuery('input[name=xml_or_all]:checked').val()
  	}
  	jQuery.post( ajaxurl, data, function( response ) {
  		jQuery('#zip-'+response).val(response);
		});
		return false;
  });
});  