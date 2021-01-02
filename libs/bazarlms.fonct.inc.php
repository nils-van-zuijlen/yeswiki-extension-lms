<?php
/**
 * Extension of bazar for the LMS module
 *
 * @category YesWiki
 * @package  lms
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

use YesWiki\Lms\Controller\CourseController;
use YesWiki\Lms\Course;
use YesWiki\Lms\Service\CourseManager;

/**
 * Display the 'Précédent', 'Suivant' and 'Fait !' buttons which permits to a learner to navigate in an activity page
 * Must be declare in the bazar form definition as followed :
 *    'navigationactivite***bf_navigation*** *** *** *** *** *** *** *** ***'
 * The second position value is the name of the entry field.
 * If the word 'module_modal' is written at the third position, the links which refer to the modules are opened in a
 * modal box.
 *
 * cf. formulaire.fonct.inc.php of the bazar extension to see the other field definitions
 *
 * @param array $formtemplate
 * @param array $tableau_template The bazar field definition inside the form definition
 * @param string $mode Action type for the form : 'saisie', 'requete', 'html', ...
 * @param array $fiche The entry which is displayed or modified
 * @return string Return the generated html to include
 */
function navigationactivite(&$formtemplate, $tableau_template, $mode, $fiche)
{

    // load the lms lib
    require_once LMS_PATH . 'libs/lms.lib.php';

    $courseController = $GLOBALS['wiki']->services->get(CourseController::class);

    // the tag of the current activity page
    $currentActivityTag = !empty($fiche['id_fiche']) ? $fiche['id_fiche'] : '';

    $output = '';
    if ($mode == 'html' && $currentActivityTag) {
        if ($GLOBALS['wiki']->config['lms_config']['use_tabs']) {
            // if a number is at the end of the page tag, it means that it's a tab page corresponding to the page without the number
            // thus, to associate this tab page to its parent one, we remove the number from the page tag
            $currentActivityTag = preg_replace('/[0-9]*$/', '', $currentActivityTag);
        }

        // the consulted course entry
        $course = $courseController->getContextualCourse();
        // the consulted module entry to display the current activity
        $module = $courseController->getContextualModule($course);

        // true if the module links are opened in a modal box
        $moduleModal = $tableau_template[2] == 'module_modal';

        if ($course && $module && !empty($module->getActivities())) {
            $output .= '<nav aria-label="navigation"' . (!empty($tableau_template[1]) ? ' data-id="' . $tableau_template[1]
                    . '"' : '') . '>
            <ul class="pager pager-lms">';

            // display the previous button
            if ($currentActivityTag == $module->getFirstActivityTag()) {
                // if first activity of a module, the previous link is to the current module entry
                $handler = ''; //($course->isModuleScripted()) ? 'saveprogress' : '' ;
                $output .= '<li class="previous"><a href="'
                    . $GLOBALS['wiki']->href($handler, $module->getTag(), ['parcours' => $course->getTag()])
                    . '"' . ($moduleModal ? ' class="bazar-entry modalbox"' : '')
                    . '><span aria-hidden="true">&larr;</span>&nbsp;' . _t('LMS_PREVIOUS') . '</a></li>';
            } elseif ($previousActivity = $module->getPreviousActivity($currentActivityTag)) {
                // otherwise, the previous link is to the previous activity
                $handler = ''; //($course->isActivityScripted()) ? 'saveprogress' : '' ;
                $output .= '<li class="previous"><a href="'
                    . $GLOBALS['wiki']->href(
                        $handler,
                        $previousActivity->getTag(),
                        ['parcours' => $course->getTag(), 'module' => $module->getTag()]
                    )
                    . '"><span aria-hidden="true">&larr;</span>&nbsp;' . _t('LMS_PREVIOUS') . '</a></li>';
            }

            // display the next button
            if ($currentActivityTag == $module->getLastActivityTag()) {
                if ($module->getTag() != $course->getLastModuleTag()
                    && $nextModule = $course->getNextModule($module->getTag())) {
                    // if the current page is the last activity of the module and the module is not the last one,
                    // the next link is to the next module entry
                    // (no next button is showed for the last activity of the last module)
                    $handler = '';($course->isModuleScripted()) ? 'saveprogress' : '' ;
                    $output .= '<li class="next"><a href="'
                        . $GLOBALS['wiki']->href($handler, $nextModule->getTag(), ['parcours' => $course->getTag()])
                        . '"' . ($moduleModal ? ' class="bazar-entry modalbox"' : '')
                        . '>' . _t('LMS_NEXT') . '&nbsp;<span aria-hidden="true">&rarr;</span></a></li>';
                }
            } else {
                // otherwise, the current activity is not the last of the module and the next link is set to the next activity
                if ($nextActivity = $module->getNextActivity($currentActivityTag)) {
                    $handler = ''; //($course->isActivityScripted()) ? 'saveprogress' : '' ;
                    $output .= '<li class="next"><a href="'
                        . $GLOBALS['wiki']->href(
                            $handler,
                            $nextActivity->getTag(),
                            ['parcours' => $course->getTag(), 'module' => $module->getTag()]
                        )
                        . '">' . _t('LMS_NEXT') . '&nbsp;<span aria-hidden="true">&rarr;</span></a></li>';
                }
            }

            $output .= '</ul>
            </nav>';
        }
    }
    return $output;
}

/**
 * Display the different options to navigate into a module according to module field 'Activé' and the navigation of the learner.
 * Must be declare in the bazar form definition as followed :
 *    'navigationmodule**bf_navigation*** *** *** *** *** *** *** *** ***'
 * The second position value is the name of the entry field.
 *
 * cf. formulaire.fonct.inc.php of the bazar extension to see the other field definitions
 *
 * @param array $formtemplate
 * @param array $tableau_template The bazar field definition inside the form definition
 * @param string $mode Action type for the form : 'saisie', 'requete', 'html', ...
 * @param array $fiche The entry which is displayed or modified
 * @return string Return the generated html to include
 */
function navigationmodule(&$formtemplate, $tableau_template, $mode, $fiche)
{
    $courseController = $GLOBALS['wiki']->services->get(CourseController::class);
    $courseManager = $GLOBALS['wiki']->services->get(CourseManager::class);

    // the tag of the current module page
    $currentModuleTag = !empty($fiche['id_fiche']) ? $fiche['id_fiche'] : '';

    // does the entry is viewed inside a modal box ? $moduleModal is true when the page was called in ajax
    $moduleModal = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    $output = '';
    if ($mode == 'html' && $currentModuleTag) {
        // load the lms lib
        require_once LMS_PATH . 'libs/lms.lib.php';
        // add LMS extension css style
        $GLOBALS['wiki']->AddCSSFile(LMS_PATH . 'presentation/styles/lms.css');

        // the consulted course entry
        $course = $courseController->getContextualCourse();
        // the consulted module entry to display the current activity
        $module = $courseManager->getModule($currentModuleTag, $fiche);

        if ($course && $module) {
            // check the access to the module
            if (empty($module->getActivities()) ||
                    (!empty($module->getField('listeListeOuinonLmsbf_actif'))
                        && $module->getField('listeListeOuinonLmsbf_actif') == 'non')
                ) {
                $output .= '<nav aria-label="navigation"' . (!empty($tableau_template[1]) ? ' data-id="' . $tableau_template[1]
                        . '"' : '') . '> 
                <ul class="pager pager-lms">';
                if (!$GLOBALS['wiki']->userIsAdmin()) {
                    // if the module has any activity or if the module is desactivated, inform the learner he doesn't have access to him
                    $output .= '<li class="noaccess">' . _t('LMS_MODULE_NOACCESS') . '</li>';
                } else {
                    // for an admin, inform him and let a button to access to the first activity
                    $output .= '<li class="noaccess"><div>' . _t('LMS_MODULE_NOACCESS_ADMIN') . '</div>'
                        . '<div class="admin-access"><a href="'
                        . $GLOBALS['wiki']->href(
                            '',
                            $module->getFirstActivityTag(),
                            ['parcours' => $course->getTag(), 'module' => $currentModuleTag]
                        )
                        . '">' . _t('LMS_BEGIN_NOACCESS_ADMIN') . '</a></div></li>';
                }
            } else {
                // otherwise display the button 'Commencer'
                $firstActivityTag = $module->getFirstActivityTag();
                $handler = ''; //($course->isActivityScripted()) ? 'saveprogress' : '' ;
                $output .= '<li class="center lms-begin"><a class="launch-module" href="'
                    . $GLOBALS['wiki']->href(
                        $handler,
                        $module->getFirstActivityTag(),
                        ['parcours' => $course->getTag(), 'module' => $currentModuleTag]
                    )
                    . '">' . _t('LMS_BEGIN') . '</a></li>';
            }

            // we show the previous and next module's buttons only if it's in a modal
            if ($moduleModal) {
                $output .= displayNextModuleButtons($currentModuleTag, $course, $moduleModal);
            }

            $output .= '</ul>
                </nav>';
        }
    }
    return $output;
}

/**
 * Display the previous and next buttons for the current module
 * @param string $currentModuleTag the current module tag
 * @param Course $course the current course
 * @param bool $moduleModal if the module is displayed in a modal
 * @return string the generated output
 */
function displayNextModuleButtons(string $currentModuleTag, Course $course, bool $moduleModal): string
{
    $output = '';
    $handler = ''; //($course->isModuleScripted()) ? 'saveprogress' : '' ;
    // display the module next button
    if ($currentModuleTag != $course->getLastModuleTag()) {
        // if not the last module of the course, a link to the next module is displayed
        if ($nextModule = $course->getNextModule($currentModuleTag)) {
            $output .= '<li class="next square" title="' . _t('LMS_MODULE_NEXT')
                . '"><a href="'
                . $GLOBALS['wiki']->href($handler, $nextModule->getTag(), ['parcours' => $course->getTag()])
                . '" "aria-label="' . _t('LMS_NEXT')
                . '"' . ($moduleModal ? ' class="bazar-entry modalbox"' : '')
                . '>' . '<i class="fa fa-caret-right" aria-hidden="true"></i></a></li>';
        }
    }
    // display the module previous button
    if ($currentModuleTag != $course->getFirstModuleTag()) {
        // if not the first module of the course, a link to the previous module is displayed
        if ($previousModule = $course->getPreviousModule($currentModuleTag)) {
            $output .= '<li class="next square" title="' . _t('LMS_MODULE_PREVIOUS')
                . '"><a href="'
                . $GLOBALS['wiki']->href($handler, $previousModule->getTag(), ['parcours' => $course->getTag()])
                . '" "aria-label="' . _t('LMS_PREVIOUS')
                . '"' . ($moduleModal ? ' class="bazar-entry modalbox"' : '')
                . '><i class="fa fa-caret-left" aria-hidden="true"></i></a></li>';
        }
    }
    return $output;
}

/**
 * Display the 'Return' button which permit to come back to the calling page (history back). The button is displayed only
 * in 'view' mode and if the entry is not opened from a modal.
 * Must be declare in the bazar form definition as followed :
 *    'boutonretour*** *** *** *** *** *** *** *** *** ***'
 *
 * cf. formulaire.fonct.inc.php of the bazar extension to see the other field definitions
 *
 * @param array $formtemplate
 * @param array $tableau_template The bazar field definition inside the form definition
 * @param string $mode Action type for the form : 'saisie', 'requete', 'html', ...
 * @param array $fiche The entry which is displayed or modified
 * @return string Return the generated html to include
 */
function boutonretour(&$formtemplate, $tableau_template, $mode, $fiche)
{

    // the tag of the current entry
    $currentEntryTag = !empty($fiche['id_fiche']) ? $fiche['id_fiche'] : '';

    if ($mode == 'html' && $currentEntryTag) {
        // does the entry is viewed inside a modal box ? $moduleModal is true when the page was called in ajax
        $entryModal = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

        // display the button if it's not inside a modal box
        if (!$entryModal) {
            return '<div class="BAZ_boutonretour" style="margin-top: 30px;"><a class="btn btn-xs btn-secondary-1" href="javascript:history.back()">'
                . '<i class="fas fa-arrow-left"></i>&nbsp;' . _t('LMS_RETURN_BUTTON') . '</a></div>';
        }
    }
}

/**
 * Display the possible reactions to comment an activity.
 * Must be declare in the bazar form definition as followed :
 *    'reactions***idreaction1,idreaction2,idreaction3***titlereaction1,titlereaction2,titlereaction3***image1,image2,image3*** *** *** *** *** *** ***'
 * Some ids are generic and have associated images and titles : j-ai-appris,j-aime,pas-clair,pas-compris,pas-d-accord,top-gratitude
 * otherwise, you will need to give a filename that is included in files directory
 *
 * cf. formulaire.fonct.inc.php of the bazar extension to see the other field definitions
 *
 * @param array $formtemplate
 * @param array $tableau_template The bazar field definition inside the form definition
 * @param string $mode Action type for the form : 'saisie', 'requete', 'html', ...
 * @param array $fiche The entry which is displayed or modified
 * @return string Return the generated html to include
 */
function reactions(&$formtemplate, $tableau_template, $mode, $fiche)
{

    // the tag of the current entry
    $currentEntryTag = !empty($fiche['id_fiche']) ? $fiche['id_fiche'] : '';

    if ($mode == 'html' && $currentEntryTag && !empty($fiche['listeListeOuinonLmsbf_reactions']) && $fiche['listeListeOuinonLmsbf_reactions'] == "oui") {
        // load the lms lib
        require_once LMS_PATH . 'libs/lms.lib.php';

        $ids = explode(',', $tableau_template[2]);
        $ids = array_map('trim', $ids);
        // if empty, we use default values
        if (count($ids) == 1 && empty($ids[0])) {
            $ids = ['top-gratitude', 'j-aime', 'j-ai-appris', 'pas-compris', 'pas-d-accord', 'idee-noire'];
        }
        $titles = explode(',', $tableau_template[3]);
        $titles = array_map('trim', $titles);
        $images = explode(',', $tableau_template[4]);
        $images = array_map('trim', $images);
        // TODO : check realpath for security
        // $images = array_map('realpath', $images);
        $outputreactions = '';
        // get reactions numbers for templating later
        $r = getAllReactions($fiche['id_fiche'], $ids, $GLOBALS['wiki']->getUserName());

        foreach ($ids as $k => $id) {
            if (empty($titles[$k])) { // if ids are default ones, we have some titles
                switch ($id) {
                    case 'j-ai-appris':
                        $title = "J'ai appris quelque chose";
                        break;
                    case 'j-aime':
                        $title = "J'aime";
                        break;
                    case 'idee-noire':
                        $title = "Ca me perturbe";
                        break;
                    case 'pas-compris':
                        $title = "J'ai pas compris";
                        break;
                    case 'pas-d-accord':
                        $title = "Je ne suis pas d'accord";
                        break;
                    case 'top-gratitude':
                        $title = "Gratitude";
                        break;
                    default:
                        $title = $id;  // we show just the id, as it's our only information available
                        break;
                }
            } else {
                $title = $titles[$k]; // custom title
            }
            if (empty($images[$k])) { // if ids are default ones, we have some images
                switch ($id) {
                    case 'j-ai-appris':
                    case 'j-aime':
                    case 'idee-noire':
                    case 'pas-compris':
                    case 'pas-d-accord':
                    case 'top-gratitude':
                        $image = LMS_PATH . 'presentation/images/mikone-' . $id . '.svg';
                        break;
                    default:
                        $image = false;
                        break;
                }
            } else {
                if (file_exists('files/' . $images[$k])) { // custom image in files folder
                    $image = 'files/' . $images[$k];
                } elseif (file_exists(LMS_PATH . 'presentation/images/mikone-' . $images[$k] . '.svg')) {
                    $image = LMS_PATH . 'presentation/images/mikone-' . $id . '.svg';
                } else {
                    $image = false;
                }
            }
            if (!$image) {
                $reaction = '<div class="alert alert-danger">Image non trouvée...</div>';
            } else {
                $nbReactions = $r['reactions'][$id];
                $reaction = '<img class="reaction-img" alt="icon ' . $id . '" src="' . $image . '" />
                    <h6 class="reaction-title">' . $title . '</h6>
                    <div class="reaction-numbers">' . $nbReactions . '</div>';
            }
            $outputreactions .= '<div class="reaction-content">';
            if ($GLOBALS['wiki']->getUser()) {
                $extraClass = (!empty($r['userReaction']) && $id == $r['userReaction']) ? ' user-reaction' : '';
                $outputreactions .= '<a href="' . $GLOBALS['wiki']->href(
                    'reaction',
                    '',
                    'id=' . $id
                ) . '" class="add-reaction' . (!empty($extraClass) ? '' . $extraClass : '') . '">' . $reaction . '</a>';
            } else {
                $outputreactions .= '<a href="#" onclick="return false;" title="Pour réagir, identifiez-vous!" class="disabled add-reaction">' . $reaction . '</a>';
            }
            $outputreactions .= '</div>';
        }
        if ($GLOBALS['wiki']->getUser()) {
            $msg = 'Partagez votre réaction à propos de ce contenu';
        } else {
            $msg = 'Pour vous permettre de réagir, <a href="#LoginModal" class="btn btn-primary" data-toggle="modal">veuillez vous identifier</a>';
        }
        $output = '<hr /><div class="reactions-container"><h5>' . $msg . '</h5><div class="reactions-flex">' . $outputreactions . '</div>';
        if ($GLOBALS['wiki']->getUser()) {
            $output .= '<em>Et n\'hésitez pas à faire un commentaire pour approndir la réflexion!</em>';
        }
        $output .= '</div>' . "\n";
        return $output;
    }
}
