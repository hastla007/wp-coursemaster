<?php
// Fixture for the negative REC-020 test: an own option that is not in the manifest.
update_option( 'course_captain_forgotten_flag', 1 );
wp_schedule_single_event( time(), 'ccap_forgotten_job' );
update_option( 'unrelated_core_option', 1 );
