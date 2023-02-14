<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_objectfs;

use tool_objectfs\local\store\object_file_system;
use tool_objectfs\local\manager;
use tool_objectfs\tests\test_file_system;

/**
 * Test basic operations of delay file delete.
 *
 * @covers \tool_objectfs\local\store\delay_file_delete
 */
class delay_file_delete extends tests\testcase {

    public function set_externalclient_config($key, $value) {
        // Get a reflection of externalclient object as a property.
        $reflection = new \ReflectionClass($this->filesystem);
        $externalclientref = $reflection->getParentClass()->getProperty('externalclient');
        $externalclientref->setAccessible(true);

        // Get a reflection of externalclient->$key property.
        $property = new \ReflectionProperty($externalclientref->getValue($this->filesystem), $key);
        $property->setAccessible(true);

        // Set new value for externalclient->$key property.
        $property->setValue($externalclientref->getValue($this->filesystem), $value);
    }

    public function error_surpressor() {
        // We do nothing. We cant surpess warnings
        // normally because phpunit will still fail.
    }

    public function test_object_storage_deleter_can_delete_object_if_delaydeleteexternalobject_is_on_and_object_is_duplicated() {
        global $CFG, $DB;

        $CFG->forced_plugin_settings['tool_objectfs']['delaydeleteexternalobject'] = 3600;
        $this->filesystem = new test_file_system();
        $file = $this->create_duplicated_file('file2');
        $filehash = $file->get_contenthash();
        $objectrecord = $DB->get_record('tool_objectfs_objects',array('contenthash' => $filehash));
        manager::update_object($objectrecord, OBJECT_LOCATION_ORPHANED);
        $this->assertTrue($this->is_externally_readable_by_hash($filehash));
    }

    public function presigned_url_should_redirect_provider() {
        $provider = array();

        // Testing defaults.
        $provider[] = array('Default', 'Default', false);

        // Testing $enablepresignedurls.
        $provider[] = array(1, 'Default', true);
        $provider[] = array('1', 'Default', true);
        $provider[] = array(0, 'Default', false);
        $provider[] = array('0', 'Default', false);
        $provider[] = array('', 'Default', false);
        $provider[] = array(null, 'Default', false);

        // Testing $presignedminfilesize.
        $provider[] = array(1, 0, true);
        $provider[] = array(1, '0', true);
        $provider[] = array(1, '', true);

        // Testing minimum file size to be greater than file size.
        // 12 is a size of the file with 'test content' content.
        $provider[] = array(1, 13, false);
        $provider[] = array(1, '13', false);

        // Testing minimum file size to be less than file size.
        // 12 is a size of the file with 'test content' content.
        $provider[] = array(1, 11, true);
        $provider[] = array(1, '11', true);

        // Testing nulls and empty strings.
        $provider[] = array(null, null, false);
        $provider[] = array(null, '', false);
        $provider[] = array('', null, false);
        $provider[] = array('', '', false);

        return $provider;
    }

    /**
     * Data provider for test_get_expiration_time_method_if_supported().
     *
     * @return array
     */
    public function get_expiration_time_method_if_supported_provider() {
        $now = time();

        // Seconds after the minute from X.
        $secondsafternow = ($now % MINSECS);
        $secondsafternowsub100 = $secondsafternow;
        $secondsafternowadd30 = $secondsafternow;
        $secondsafternowadd100 = $secondsafternow;
        $secondsafternowaddweek = ($now + WEEKSECS) % MINSECS;

        return [
            // Default Pre-Signed URL expiration time and int-like 'Expires' header.
            [7200, $now, 0, $now + 7200 + MINSECS - $secondsafternow],
            [7200, $now, $now - 100, $now + (2 * MINSECS) - $secondsafternowsub100],
            [7200, $now, $now + 30, $now + (2 * MINSECS) - $secondsafternowadd30],
            [7200, $now, $now + 100, $now + (2 * MINSECS) - $secondsafternowadd100],
            [7200, $now, $now + WEEKSECS + HOURSECS, $now + WEEKSECS - MINSECS - $secondsafternowaddweek],

            // Default Pre-Signed URL expiration time and string-like 'Expires' header.
            [7200, $now, 'Thu, 01 Jan 1970 00:00:00 GMT', $now + 7200 + MINSECS - $secondsafternow],
            [7200, $now, userdate($now - 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowsub100],
            [7200, $now, userdate($now + 30, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd30],
            [7200, $now, userdate($now + 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd100],
            [7200, $now, userdate($now + WEEKSECS + HOURSECS, '%a, %d %b %Y %H:%M:%S'), $now + WEEKSECS - MINSECS - $secondsafternowaddweek],

            // Custom Pre-Signed URL expiration time and int-like 'Expires' header.
            [0, $now, 0, $now + (2 * MINSECS) - $secondsafternow],
            [600, $now, 0, $now + 600 + MINSECS - $secondsafternow],
            [600, $now, $now - 100, $now + (2 * MINSECS) - $secondsafternowsub100],
            [600, $now, $now + 30, $now + (2 * MINSECS) - $secondsafternowadd30],
            [600, $now, $now + 100, $now + (2 * MINSECS) - $secondsafternowadd100],
            [600, $now, $now + WEEKSECS + HOURSECS, $now + WEEKSECS - MINSECS - $secondsafternowaddweek],

            // Custom Pre-Signed URL expiration time and string-like 'Expires' header.
            [0, $now, 'Thu, 01 Jan 1970 00:00:00 GMT', $now + (2 * MINSECS) - $secondsafternow],
            [600, $now, 'Thu, 01 Jan 1970 00:00:00 GMT', $now + 600 + MINSECS - $secondsafternow],
            [600, $now, userdate($now - 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowsub100],
            [600, $now, userdate($now + 30, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd30],
            [600, $now, userdate($now + 100, '%a, %d %b %Y %H:%M:%S'), $now + (2 * MINSECS) - $secondsafternowadd100],
            [600, $now, userdate($now + WEEKSECS + HOURSECS, '%a, %d %b %Y %H:%M:%S'), $now + WEEKSECS - MINSECS - $secondsafternowaddweek],
        ];
    }

    /**
     * Data provider for test_get_valid_http_ranges().
     *
     * @return array
     */
    public function get_valid_http_ranges_provider() {
        return [
            ['', 0, false],
            ['bytes=0-', 100, (object)['rangefrom' => 0, 'rangeto' => 99, 'length' => 100]],
            ['bytes=0-49/100', 100, (object)['rangefrom' => 0, 'rangeto' => 49, 'length' => 50]],
            ['bytes=50-', 100, (object)['rangefrom' => 50, 'rangeto' => 99, 'length' => 50]],
            ['bytes=50-80/100', 100, (object)['rangefrom' => 50, 'rangeto' => 80, 'length' => 31]],
        ];
    }

    /**
     * Data provider for test_curl_range_request_to_presigned_url().
     *
     * @return array
     */
    public function curl_range_request_to_presigned_url_provider() {
        return [
            ['15-bytes string', (object)['rangefrom' => 0, 'rangeto' => 14, 'length' => 15], '15-bytes string'],
            ['15-bytes string', (object)['rangefrom' => 0, 'rangeto' => 9, 'length' => 10], '15-bytes s'],
            ['15-bytes string', (object)['rangefrom' => 5, 'rangeto' => 14, 'length' => 10], 'tes string'],
        ];
    }
}
