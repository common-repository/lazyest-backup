<?php
/*
Plugin Name: Lazyest Backup
Plugin URI: http://brimosoft.nl/lazyest/backup/
Description: Backup for Lazyest Gallery 
Date: 2012, December
Author: Brimosoft
Author URI: http://brimosoft.nl
Version: 0.3.1
License: GNU GPLv2
*/

/**
 * LazyestBackup
 * 
 * @package Lazyest Gallery
 * @subpackage Lazyest Backup
 * @author Marcel Brinkkemper
 * @copyright 2011-2012 Marcel Brinkkemper
 * @version 0.3.1
 * @access public
 */
class LazyestBackup {
	
	var $zipbase;
	var $zipfile;
	var $downloadfile;
	var $pattern;
	
	/**
	 * LazyestBackup::__construct()
	 * 
	 * @since 0.1.0
	 * @uses add_action()
	 * @uses wp_upload_dir()
	 * @uses trailingslashit()
	 * @return void
	 */
	function __construct() {
		$this->zipbase = 'gallery-backup.zip';
		$uploads = wp_upload_dir();		
		$this->zipfile =  str_replace( '\\', '/', trailingslashit( $uploads['basedir'] ) . $this->zipbase );
		$this->downloadfile =  str_replace( '\\', '/', trailingslashit( $uploads['baseurl'] ) . $this->zipbase );
		$options = get_option( 'lazyest-backup' );	
		$this->pattern = ( isset( $options['pattern'] ) ) ? $options['pattern'] : 'xml';
		$this->xmlpattern = 'xml';	
		add_action( 'admin_menu', array( &$this, 'add_pages' ), 20 ); 
		add_action( 'admin_action_lazyest-backup', array( &$this, 'do_action' ) );
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
		add_action( 'wp_ajax_lazyest_zip_folder', array( &$this, 'lazyest_zip_folder' ) );
		add_action( 'wp_ajax_lazyest_restore_hundred', array( &$this, 'lazyest_restore_hundred' ) );
		add_action( 'wp_ajax_lazyest_pattern', array( &$this, 'lazyest_pattern' ) );
		add_action( 'wp_ajax_lazyest_backup_notice', array( &$this, 'lazyest_backup_notice' ) );
		
		load_plugin_textdomain( 'lazyest-backup', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
	
	/**
	 * LazyestBackup::add_pages()
	 * 
	 * @since 0.1.0
	 * @uses add_submenu_page()
	 * @uses add_action()
	 * @return void
	 */
	function add_pages() {		
		$menu_page = add_submenu_page('lazyest-filemanager', __('Lazyest Gallery Backup', 'lazyest-backup' ), __( 'Backup', 'lazyest-backup' ), 'manage_options', 'lazyest-backup', array( &$this, 'admin_page' ) );
		add_action( "admin_print_styles-{$menu_page}", array( &$this, 'admin_css' ) );
		add_action( "admin_print_scripts-{$menu_page}", array( &$this,  'admin_js' ) );		
	}
	
	/**
	 * LazyestBackup::admin_css()
	 * 
	 * @since 0.1.0
	 * @uses wp_enqueue_style()
	 * @return void
	 */
	function admin_css() {
		wp_enqueue_style( 'lazyest_backup', plugins_url( 'css/admin.css',  __FILE__ ) );
	}
	
	/**
	 * LazyestBackup::admin_js()
	 * 
	 * @since 0.1.0
	 * @uses wp_enqueue_script()
	 * @uses wp_localize_script()
	 * @return void
	 */
	function admin_js() {
		global $lg_gallery;
	 	wp_enqueue_script( 'lg_progressbar' );		
		wp_enqueue_script( 'lazyest_backup', plugins_url( 'js/ajax.js',  __FILE__ ), array( 'lg_progressbar' ), true );
		wp_localize_script( 'lazyest_backup', 'lazyest_backup', $this->localize_script() );
	}	
	
	function localize_script() {
		global $lg_gallery;
		return array(
			'Ready' => esc_attr__( 'Ready', 'lazyest-backup' ),
			'Archiving' => __( 'The archive process is currently running', 'lazyest-backup' ),			
			'Restoring' => __( 'The restore process is currently running', 'lazyest-backup' ),
			'Analyzing' =>	esc_html__( 'Analyzing...', 'lazyest-backup' ),
			'busyArchiving' =>  esc_html__( 'Archiving...', 'lazyest-backup' ),
			'busyRestoring' => esc_html__( 'Restoring...', 'lazyest-backup' ),
			'boxImage' => $lg_gallery->plugin_url . '/images/progressbar.gif',
      'barImage' => $lg_gallery->plugin_url . '/images/progressbg_green.gif'
		);
	}
	
	/**
	 * LazyestBackup::admin_notices()
	 * Custom admin notices
	 * 
	 * @since 0.1.0
	 * @uses esc_html__()
	 * @return void
	 */
	function admin_notices() {
		
		function upload_error( $error ) {
			$result = '';
			switch( $error ) {
				case -2: $result = esc_html__( 'This is not a valid archive', 'lazyest-backup' ); break;
				case -1: $result = esc_html__( 'Could not rename existing archive', 'lazyest-backup' ); break;
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE : $result = esc_html__( 'This archive exceeds the maximum upload size', 'lazyest-backup' ); break;
				case UPLOAD_ERR_PARTIAL : $result = esc_html__( 'The archive was only partially uploaded.', 'lazyest-backup' ); break;
				case UPLOAD_ERR_NO_FILE:
				default : $result = esc_html__( 'An error occurred, no archive was uploaded.', 'lazyest-backup' ); break;
			}
			return $result;
		}
		
		function restore_error( $error ) {
			$result = sprintf( esc_html__('%s files restored', 'lazyest-backup' ), $error );
			if ( 1 > $error ) {
				switch( $error ) {
					case  0: $result = esc_html__( 'The archive does not contain Lazyest Gallery files', 'lazyest-backup' ); break;
					case -1: $result = esc_html__( 'The archive does not exist', 'lazyest-backup' ); break; 					
					case -2: $result = esc_html__( 'Could not open the archive', 'lazyest-backup' ); break;
					case -3: $result = esc_html__( 'An error occurred during restore', 'lazyest-backup' ); break;
				}
			}
			return $result;
		}
		
		if ( $notice = get_transient( 'lazyest_backup_notice') ) {
			delete_transient( 'lazyest_backup_notice');
			$message = '';
			if ( isset( $notice['action'] ) ) {
				$result = $notice['result'];
				switch( $notice['action'] ) {
					case 'created' : 
						$message = ( $result == 'true' ) ? 
							esc_html__( 'Archive created successfully', 'lazyest-backup' ) :
							esc_html__( 'Could not create Archive', 'lazyest-backup' );
						$class = ( $result == 'true' )? 'updated' : 'error';
						break; 
					case 'erased' :
						$message = ( $result == 'true' ) ? 
							esc_html__( 'Archive erased successfully', 'lazyest-backup' ) :
							esc_html__( 'Could not erase Archive', 'lazyest-backup' );
						$class = ( $result == 'true' )? 'updated' : 'error';
						break;	
					case 'uploaded' :
						$message = ( $result == 'true' ) ? 
							sprintf( esc_html__( 'Archive uploaded successfully to %s', 'lazyest-backup' ), 'lazyest-backup.zip' ):
							upload_error( intval( $result ) );
						$class = ( $result == 'true' )? 'updated' : 'error';
						break;
					case 'restored' :
						$message = restore_error( intval( $result ) );
						$class = ( intval( $result ) > 0 )? 'updated' : 'error';
						break;		
					default: return; break;
				} 	
			}
			if ( '' != $message )
				echo "<div class='$class'><p>$message</p></div>";				
		}
	}
	
	/**
	 * LazyestBackup::lazyest_backup_notice()
	 * Changes admin notice values on AJAX call
	 * @return void
	 */
	function lazyest_backup_notice() {		
		$message = array( 'action' => $_POST['process'], 'result' => $_POST['result'] );
		set_transient( 'lazyest_backup_notice', $message, 60 );
		echo 1;
		die();
	}
	
	/**
	 * LazyestBackup::_all_pattern()
	 * returns allowed types for Lazyest Gallery
	 * 
	 * @since 0.2.0
	 * @return string search pattern for file extensions
	 */
	protected function _all_pattern() {
		global $lg_gallery;
		return str_replace( ' ', '|', $lg_gallery->get_option( 'fileupload_allowedtypes' ) ) . '|xml';
	} 
	
	/**
	 * LazyestBackup::do_action()
	 * Perform actions based on $_POST
	 * 
	 * @since 0.1.0
	 * @uses wp_verify_nonce()
	 * @uses admin_url()
	 * @uses add_query_arg()
	 * @uses wp_redirect()
	 * @return void
	 */
	function do_action() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '';
		$message = array( 'action' => '', 'result' => '' );
		if ( wp_verify_nonce( $nonce, 'lazyest_backup') ) {
			if ( isset( $_REQUEST['create'] ) )
				$message = $this->create_archive();
			else if ( isset( $_REQUEST['erase'] ) )
				$message = $this->erase_archive();	
			else if ( isset( $_REQUEST['upload'] ) )
				$message = $this->upload_archive();
			else if ( isset( $_REQUEST['restore'] ) )
				$message = $this->restore_archive();						
		}
		$options = array();
		$options['pattern'] = $_REQUEST['xml_or_all'] == 'xml' ? 'xml' : $this->_all_pattern();
		update_option('lazyest-backup', $options );
		set_transient( 'lazyest_backup_notice', $message, 60 );
		$redirect = admin_url( 'admin.php?page=lazyest-backup' );		
		wp_redirect( $redirect );
    exit();
	}
	
	/**
	 * LazyestBackup::lazyest_pattern()
	 * Changes the search pattern on AJAX call
	 * @return void
	 */
	function lazyest_pattern() {
		$options = array();
		$options['pattern'] = $_REQUEST['xml_or_all'] == 'xml' ? 'xml' : $this->_all_pattern();
		update_option('lazyest-backup', $options );
		echo ( $options['pattern'] == 'xml' ) ? 'xml' : 'all'; 
		die();
	}
	
	/**
	 * LazyestBackup::_build_folders_array()
	 * 
	 * @ since 0.3.0
	 * @param string $root
	 * @return array of utf8 encoded and url encoded folder names
	 */
	function _build_folders_array( $root = '' ) {
		global $lg_gallery;
    $folders = array();
    if ( ! isset( $lg_gallery->root ) )
    	return;
    $root = ( $root == '' ) ? $lg_gallery->root : $root;
    if ( $lg_gallery->is_dangerous( $root ) || ! file_exists( $root ) )
    	return;    	
    if ( $dir_handler = @opendir( $root ) ) {
      while ( false !== ( $afile = readdir( $dir_handler ) ) ) {
        if ( $lg_gallery->valid_dir( $root . $afile ) ) {
          $folders[] = urlencode( utf8_encode( $root . $afile ) );
          $folders = array_merge( $folders, $this->_build_folders_array( $root . trailingslashit( $afile ) ) );
        } else {
          continue;
        }
      }
      @closedir( $dir_handler );
      return $folders;
    } 
  }
	
	/**
	 * LazyestBackup::zip_folder()
	 * Zip a folder in folder array
	 * 
	 * @since 0.1.0
	 * @uses get_transient() 
	 * @uses set_transient()
	 * @uses path_join()
	 * @uses delete_transient()
	 * @param int $i array index
	 * @return int next array index
	 */
	function zip_folder( $i ) {
		global $lg_gallery;
		$folder_array = get_transient( 'lg_zip_folders' );
		$what = $this->pattern;
		if ( false === $folder_array ) {    
      $folder_array = $this->_build_folders_array();
      set_transient(  'lg_zip_folders', $folder_array, 300 );  
    }
    $zip = new ZipArchive; 
    $j = ( $i == 0 ) ? count( $folder_array ) : $i;
    if ( $j < count( $folder_array ) ) {	    	
    	if ( $zip->open( $this->zipfile, ZIPARCHIVE::CHECKCONS ) === TRUE) {
    		$this_folder = utf8_decode( urldecode( $folder_array[$j-1] ) );
	      $directory =substr( $this_folder, strlen( $lg_gallery->root ) );
	      if ( false === $zip->locateName( $directory ) )
	      	$zip->addEmptyDir( $directory );
	      $handle = @opendir( $this_folder );
	      while( $file = readdir( $handle ) ) {
	      	if ( ! is_dir( $file ) ) {
	      		if ( 0 < preg_match( "/^.*\.($what)$/i", $file ) )
	      			$zip->addFile( path_join( $this_folder, $file ), path_join( $directory, $file ) );
					}
	      }
	    $zip->close();
	    } else {	    	
	    	delete_transient( 'lg_zip_folders' ); 
	    	return -1;
	    }
    }
	  if ( $j == 1 ) 
	    delete_transient( 'lg_zip_folders' ); 
    return $j - 1;
	}
	
	/**
	 * LazyestBackup::lazyest_zip_folder()
	 * Zip a folder on AJAX call
	 * 
	 * @since 0.1.0
	 * @uses get_option()
	 * @return void
	 */
	function lazyest_zip_folder() {
		if ( ! isset( $_POST['folder'] ) )			
			die( 0 );		
		$i = $_POST['folder'];
		if ( 0 == $i ) {
			if  ( file_exists( $this->zipfile ) ) {
				@unlink( $this->zipfile );
			}			
			$zip = new ZipArchive;
			if ( $zip->open( $this->zipfile, ZIPARCHIVE::CREATE ) === TRUE )  {
				$zip->addFromString( 'lazyest-backup.txt', date( get_option( 'date_format' ), time() ) );
				$zip->close();
			} else {
				die( 0 );
			} 	
		}	
		@ini_set( 'max_execution_time', 300 );
		echo $this->zip_folder( $i );
		die();
	}
	
	/**
	 * LazyestBackup::lazyest_restore_hundred()
	 * restore 100 files on AJAX call
	 * 
	 * @since 0.2.0
	 * @return void
	 */
	function lazyest_restore_hundred() {
		global $lg_gallery;
		if ( ! isset( $_POST['hundred'] ) )			
			die( 0 );
		@ini_set( 'max_execution_time', 300 );
		$what = $this->pattern;
		$i = $j = 0;		 
		$start = intval( $_POST['hundred'] );
		$end = $start - 100;
		if  ( file_exists( $this->zipfile ) ) {
			$zip = new ZipArchive;
			if ( $zip->open( $this->zipfile ) === true ) {
				if ( $start == 0 ) {
					echo $zip->numFiles -1 . ',0';
					die();
				}
				for( $i = $start; ( ( 0 < $i ) && ( $i > $end ) ); $i-- ) {
					$file = $zip->getNameIndex( $i );
					if ( 0 < preg_match( "/^.*\.($what)$/i", $file ) ) {
						$zip->extractTo( $lg_gallery->root , array( $file ) );
						$j++;
					}					
				}	
			} 
		} 
		echo ( $start-100 > 0 ) ? $start-100 . ",$j" : 0 . ",$j";
		die();
	}
	
	/**
	 * LazyestBackup::create_archive()
	 * Zip all Folders on $_POST, when javascript is disabled
	 * 
	 * @since 0.1.0
	 * @uses get_option()
	 * @return array
	 */
	function create_archive() {
		// calling this directly will cost a lot of time
		@ini_set( 'max_execution_time', 300 );
		
		$message = array( 'action' => 'created', 'result' => 'true' );
		if  ( file_exists( $this->zipfile ) ) {
			@unlink( $this->zipfile );
		}
		$zip = new ZipArchive;
		if ( $zip->open( $this->zipfile, ZIPARCHIVE::CREATE ) === TRUE )  {
			$zip->addFromString( 'lazyest-backup.txt', date( get_option( 'date_format' ), time() ) );
			$zip->close();
			$togo = $this->zip_folder( 0 );
			while ( 0 < $togo ) {
				$togo = $this->zip_folder( $togo );
			}
		} else {
			$message['result'] = 'false';
		}
		return $message;
	}
	
	/**
	 * LazyestBackup::erase_archive()
	 * Delete the zip file
	 * 
	 * @since 0.1.0 
	 * @return array
	 */
	function erase_archive() {
		$message = array( 'action' => 'erased', 'result' => 'true' );
		if  ( file_exists( $this->zipfile ) ) {
			$message['result'] = @unlink( $this->zipfile ) ? 'true' : 'false';
		}
		return $message;
	}	
	
	/**
	 * LazyestBackup::upload_archive()
	 * Handles archive uploads
	 * Makes a copy of existing archive
	 * Test if valid zip archive
	 * 
	 * @since 0.2.0
	 * @return string/int 'true' if succes, -2 .. 8 for error codes
	 */
	function upload_archive() {	
		$message = array( 'action' => 'uploaded', 'result' => 'true' );
		
		if ( UPLOAD_ERR_OK != $_FILES['file']['error'] ) {
			$message['result'] = $_FILES['zip_upload']['error'];
			return $message; 
		}
		$temp_file = $_FILES['zip_upload']['tmp_name'];
		
		// backup current archive
		if ( file_exists( $this->zipfile ) ) {
			$backup = $this->zipfile.'.bak';
			if ( file_exists( $backup ) )
				@unlink( $backup ); 
			if ( ! @rename( $this->zipfile, $backup  ) ) {
				$message['result'] = -1; 
				return $message;
			}			
		}
		move_uploaded_file( $temp_file, $this->zipfile );
		
		$zip = new ZipArchive;
		if ( $zip->open( $this->zipfile, ZIPARCHIVE::CHECKCONS ) !== TRUE ) {
			$mesage['result'] = -2;
			if ( file_exists( $backup ) )
				@rename( $this->zipfile, $backup  );
			return $message;	
		} 
		return $message;
	}
	
	/**
	 * LazyestBackup::restore_archive()
	 * Copies images and captions.xml to gallery
	 * 
	 * @since 0.2.0
	 * @return int
	 */
	function restore_archive() {
		global $lg_gallery;
		// calling this directly will cost a lot of time
		@ini_set( 'max_execution_time', 300 );
		$what = $this->pattern;
		$message = array( 'action' => 'restored', 'result' => '0' );
		if ( ! file_exists( $this->zipfile ) ) {
			$message['result'] = -1;
			return $message;
		}
		$files_restored = 0;
		$zip = new ZipArchive;
		if ( $zip->open( $this->zipfile ) === true) {                   
			for( $i = 0; $i < $zip->numFiles; $i++ ) {
				$file = $zip->getNameIndex( $i );
				if ( 0 < preg_match( "/^.*\.($what)$/i", $file ) ) {
					$zip->extractTo( $lg_gallery->root , array( $file ) );
					$files_restored++;
				}
	    }                    
	    $zip->close();			
			$message['result'] = $files_restored;	                    
		}	else {
			$message['result'] = -2;
		}
		return $message;
	}
	
	/**
	 * LazyestBackup::current_backup()
	 * Display current archive
	 * 
	 * @return void
	 */
	function current_backup() {
		global $lg_gallery;
		$result = esc_html__( 'You have currently no archive available', 'lazyest-backup' );
		if ( file_exists( $this->zipfile ) ) {
			$time_zone = get_option( 'timezone_string' );
			$offset = 0;
			if ( ! $time_zone || '' == $time_zone ) { 
				$time_zone = 'UTC';			
				$offset =  get_option( 'gmt_offset' );
			}
			date_default_timezone_set( $time_zone );
			$datetime = filemtime( $this->zipfile ) + $offset * 3600;						
			$result = sprintf( esc_html__( '%s created on %s at %s', 'lazyest-backup' ) ,
				sprintf( "<a href='$this->downloadfile' title='%s'><strong>$this->zipbase</strong></a>", esc_html__( 'Download current archive', 'lazyest-backup' ) ),
				date( get_option( 'date_format' ), $datetime ),
				date( get_option( 'time_format'), $datetime ) 
			);
			$image = plugins_url( 'css/images/eraseit.png',  __FILE__ );
			$title = esc_html__( 'Delete this archive', 'lazyest-backup' ); 
			$result .= " <input class='erase' title='$title' type='image' src='$image' name='erase' value='erase' />";
		}
		echo "<p>$result</p>";
	}
	
	/**
	 * LazyestBackup::admin_page()
	 * The Lazyest Backup Admin page
	 * 
	 * @since 0.1.0
	 * @return void
	 */
	function admin_page() {
		$create_url = admin_url( 'admin.php' );
		$xml = 'xml' == $this->pattern;
		?>
		<div class="wrap">
			<?php screen_icon( 'backup' ); ?>
      <h2><?php echo esc_html_e( 'Backup your Gallery', 'lazyest-backup' ); ?></h2> 
      <div class="tool-box">
				<form id="lazyest-backup-form" action="<?php echo $create_url ?>" method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'lazyest_backup' );  ?>
					<input type="hidden" name="action" value="lazyest-backup" />
					<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo wp_max_upload_size(); ?>" />
	      	<h3 class="title"><?php esc_html_e( 'Your current archive', 'lazyest-backup' ); ?></h3>
	      	<?php $this->current_backup() ?>	      	
					<?php if ( file_exists( $this->zipfile ) ) : ?>
						<p class="submit">
							<a id="download-current" class="button download" href="<?php echo $this->downloadfile ?>" title="<?php esc_html_e( 'Download current archive', 'lazyest-backup' ); ?>"><?php esc_html_e( 'Download current archive', 'lazyest-backup' ); ?></a>											
						</p>
					<?php endif; ?>					
					<p class="submit">
						<input class="upload" id="upload_zip" type="submit" disabled="disabled" name="upload" class="button" accept="application/zip" value="<?php _e( 'Upload archive from Computer', 'lazyest-backup' ); ?>" /><input class="file" type="file" name="zip_upload" id="zip_upload" accept="application/zip" /> 
					</p>
					<p>
						<label for="zip-xml"><input type="radio" id="zip-xml" name="xml_or_all" value="xml" <?php checked( $xml ) ?> /> <?php esc_html_e( 'Backup / Restore captions.xml files only.', 'lazyest-backup' ); ?></label>
					</p>
					<p>	
						<label for="zip-all"><input type="radio" id="zip-all" name="xml_or_all" value="all" <?php checked( ! $xml ) ?> /> <?php esc_html_e( 'Backup / Restore images and captions.xml files.', 'lazyest-backup' ); ?></label>																
					</p>	      	 
	      	<p class="submit">	      	
						<input id="create-archive" type="submit" class="create" name="create" value="<?php esc_html_e( 'Create a new archive', 'lazyest-backup' ) ?>" /> <?php if ( file_exists( $this->zipfile ) ) : ?><input id="restore-archive" class="restore" type="submit" name="restore" value="<?php esc_html_e( 'Restore current archive', 'lazyest-backup' ); ?>" /><?php endif; ?>																		
					</p>
					<div id="zipper-div">
						<p id="backup_busy"></p>
						<p><span id="zipper-bar" class="progressBar ajax-loading"></span></p>
					</div>
      	</form>
			</div>					 
		</div>
		<?php
	}
} // LazyestBackup

// only initialize in admin
if ( is_admin() ) {
	$lazyest_backup = new LazyestBackup;
}
?>