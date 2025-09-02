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
 * Module providing functions to send requests to the AI tools.
 *
 * @module     block_ai_chat/dom_extractor
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export const getFormElements = () => {
    return {
        "domelements": [
            {
                "id": "id_name",
                "name": "name",
                "type": "text",
                "current_value": "",
                "helptext": ""
            },
            {
                "id": "id_introeditor",
                "name": "introeditor[text]",
                "type": "textarea",
                "current_value": "",
                "helptext": ""
            },
            {
                "id": "id_showdescription",
                "name": "showdescription",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Wenn diese Option aktiviert ist, wird die Beschreibung zusammen mit dem Link auf der Kursseite angezeigt.\n "
            },
            {
                "id": "id_activityeditor",
                "name": "activityeditor[text]",
                "type": "textarea",
                "current_value": "",
                "helptext": ""
            },
            {
                "id": "id_submissionattachments",
                "name": "submissionattachments",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Wenn die Option aktiviert ist, werden Dateien nur in der Abgabeseite angezeigt.\nWenn die Option deaktiviert ist, werden Dateien sowohl in der Aufgabenseite als auch in der Abgabeseite angezeigt.\n "
            },
            {
                "id": "id_allowsubmissionsfromdate_enabled",
                "name": "allowsubmissionsfromdate[enabled]",
                "type": "checkbox",
                "current_value": "1",
                "helptext": ""
            },
            {
                "id": "id_duedate_enabled",
                "name": "duedate[enabled]",
                "type": "checkbox",
                "current_value": "1",
                "helptext": ""
            },
            {
                "id": "id_cutoffdate_enabled",
                "name": "cutoffdate[enabled]",
                "type": "checkbox",
                "current_value": "1",
                "helptext": ""
            },
            {
                "id": "id_gradingduedate_enabled",
                "name": "gradingduedate[enabled]",
                "type": "checkbox",
                "current_value": "1",
                "helptext": ""
            },
            {
                "id": "id_alwaysshowdescription",
                "name": "alwaysshowdescription",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Wenn diese Option deaktiviert ist, wird die Aufgabenbeschreibung für Teilnehmer/innen nur ab dem Abgabebeginn angezeigt.\n "
            },
            {
                "id": "id_assignsubmission_mbsaudio_enabled",
                "name": "assignsubmission_mbsaudio_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Diese Methode ermöglicht den Teilnehmern eine Audioaufnahmen direkt im Browser.\n "
            },
            {
                "id": "id_assignsubmission_file_enabled",
                "name": "assignsubmission_file_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Diese Methode ermöglicht den Teilnehmern eine Audioaufnahmen direkt im Browser.\n "
            },
            {
                "id": "id_assignsubmission_onlinetext_enabled",
                "name": "assignsubmission_onlinetext_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Diese Methode ermöglicht den Teilnehmern eine Audioaufnahmen direkt im Browser.\n "
            },
            {
                "id": "id_assignsubmission_file_filetypes",
                "name": "assignsubmission_file_filetypes[filetypes]",
                "type": "text",
                "current_value": "",
                "helptext": ""
            },
            {
                "id": "id_assignsubmission_onlinetext_wordlimit",
                "name": "assignsubmission_onlinetext_wordlimit",
                "type": "text",
                "current_value": "",
                "helptext": ""
            },
            {
                "id": "id_assignsubmission_onlinetext_wordlimit_enabled",
                "name": "assignsubmission_onlinetext_wordlimit_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": ""
            },
            {
                "id": "id_assignfeedback_comments_enabled",
                "name": "assignfeedback_comments_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Bewerter/innen können Feedback-Kommentare für jede abgegebene Lösung erstellen, wenn die Funktion aktiviert wird.\n "
            },
            {
                "id": "id_assignfeedback_editpdf_enabled",
                "name": "assignfeedback_editpdf_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Bewerter/innen können Feedback-Kommentare für jede abgegebene Lösung erstellen, wenn die Funktion aktiviert wird.\n "
            },
            {
                "id": "id_assignfeedback_file_enabled",
                "name": "assignfeedback_file_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Bewerter/innen können Feedback-Kommentare für jede abgegebene Lösung erstellen, wenn die Funktion aktiviert wird.\n "
            },
            {
                "id": "id_assignfeedback_offline_enabled",
                "name": "assignfeedback_offline_enabled",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Bewerter/innen können Feedback-Kommentare für jede abgegebene Lösung erstellen, wenn die Funktion aktiviert wird.\n "
            },
            {
                "id": "id_grade_modgrade_point",
                "name": "grade[modgrade_point]",
                "type": "text",
                "current_value": "100",
                "helptext": ""
            },
            {
                "id": "id_gradepass",
                "name": "gradepass",
                "type": "text",
                "current_value": "",
                "helptext": "Diese Option legt die erforderliche Mindestbewertung (in Punkten, nicht in Prozent!) für das Bestehen fest. Der Wert wird beim Aktivitäts- und beim Kursabschluss verwendet, außerdem wird bei der Bewertung ein Bestehen in grün und ein Scheitern in rot markiert.\n "
            },
            {
                "id": "id_cmidnumber",
                "name": "cmidnumber",
                "type": "text",
                "current_value": "",
                "helptext": "Durch das Festlegen einer ID-Nummer kann die Aktivität oder das Material für Zwecke wie die Berechnung der Bewertung oder nutzerdefinierte Berichte identifiziert werden. Andernfalls kann das Feld leer bleiben.\n\nFür bewertbare Aktivitäten kann die ID-Nummer auch im Setup für Bewertungen festgelegt werden, sie kann jedoch nur auf der Seite mit den Einstellungen der Aktivität oder des Materials bearbeitet werden.\n "
            },
            {
                "id": "id_availabilityconditionsjson",
                "name": "availabilityconditionsjson",
                "type": "textarea",
                "current_value": "",
                "helptext": ""
            },
            {
                "id": "id_coursecontentnotification",
                "name": "coursecontentnotification",
                "type": "checkbox",
                "current_value": "1",
                "helptext": "Aktivieren Sie diese Option, um Teilnehmer/innen über diese neue oder geänderte Aktivität oder Ressource zu informieren. Nur Nutzer/innen, die auf die Aktivität oder Ressource zugreifen können, erhalten die Benachrichtigung.\n "
            }
        ]
    };
};
