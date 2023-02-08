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

/**

 * Task that checks for files that were delayed for deletion.
 * It deletes the files which have an expired delay time.
 *
 * @package   tool_objectfs
 * @author    Kateryna Martynenko <kateryna.martynenko@moodle.com>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

class delay_delete_external_objects extends task {

    /** @var string $stringname */
    protected $stringname = 'delay_delete_external_objects_task';

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        $timeperiodforremoval = $this->config->delaydeleteexternalobject;
        if ($timeperiodforremoval == 0) {
            mtrace('Skipping delayed deletion of the external object of the delaydeleteexternalobject is set to an empty value.');
            return;
        }

        $params = [
            'timeperiodforremoval' => time() - $timeperiodforremoval
        ];

        if (!empty($this->config->delaydeleteexternalobject)) {

            // We need to delay the deletion of the external files.
            $filesystem = new $this->config->filesystem();

            // Compare the time when the file was supposed to be deleted immideately and the time selected in the "Delay delete external object" setting.

            $sql = 'SELECT * FROM {tool_objectfs_objects} WHERE timeorphaned < :timeperiodforremoval LIMIT 1000';

            $objects = $DB->get_recordset_sql($sql, $params);
            $count = 0;

            foreach ($objects as $object) {

                // Delete the external file.
                $filesystem->delete_external_file_from_hash($object->contenthash, true);
                $DB->delete_records('tool_objectfs_objects', ['id' => $object->id]);
                $count++;
            }
            $objects->close();
            mtrace("Deleted $count files from external server");
        } 
    }
}
