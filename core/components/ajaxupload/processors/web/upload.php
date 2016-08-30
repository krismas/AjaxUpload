<?php
/**
 * AjaxUpload Processor
 *
 * @package ajaxupload
 * @subpackage processor
 *
 * @var modX $modx
 * @var array $scriptProperties
 */
// Delete uploaded images
$delete = $modx->getOption('delete', $scriptProperties, false);
$uid = htmlspecialchars(trim($modx->getOption('uid', $scriptProperties, false)));
$output = '';

if (isset($_SESSION['ajaxupload'][$uid . 'config'])) {
    $modx->ajaxupload = new AjaxUpload($modx, $_SESSION['ajaxupload'][$uid . 'config']);
    $modx->ajaxupload->initialize($_SESSION['ajaxupload'][$uid . 'config']);

    $result = array();
    if ($delete !== false) {
        if (strtolower($delete) == 'all') {
            // Delete all uploaded files/thumbs & clean session
            if (is_array($_SESSION['ajaxupload'][$uid])) {
                foreach ($_SESSION['ajaxupload'][$uid] as $fileInfo) {
                    if (file_exists($fileInfo['path'] . $fileInfo['uniqueName'])) {
                        unlink($fileInfo['path'] . $fileInfo['uniqueName']);
                        $fileInfo['uniqueName'] = '';
                    }
                    if (file_exists($fileInfo['path'] . $fileInfo['thumbName'])) {
                        unlink($fileInfo['path'] . $fileInfo['thumbName']);
                        $fileInfo['thumbName'] = '';
                    }
                    $_SESSION['ajaxupload'][$uid . 'delete'][] = $fileInfo;
                }
            }
            $_SESSION['ajaxupload'][$uid] = array();
            $result['success'] = true;
        } else {
            // Delete one uploaded file/thumb & remove session entry
            $fileId = preg_replace('/[^0-9a-f]/', '', $delete);
            if (isset($_SESSION['ajaxupload'][$uid][$fileId])) {
                $fileInfo = $_SESSION['ajaxupload'][$uid][$fileId];
                if (file_exists($fileInfo['path'] . $fileInfo['uniqueName'])) {
                    unlink($fileInfo['path'] . $fileInfo['uniqueName']);
                    $fileInfo['uniqueName'] = '';
                }
                if (file_exists($fileInfo['path'] . $fileInfo['thumbName'])) {
                    unlink($fileInfo['path'] . $fileInfo['thumbName']);
                    $fileInfo['thumbName'] = '';
                }
                $_SESSION['ajaxupload'][$uid . 'delete'][] = $fileInfo;
                unset($_SESSION['ajaxupload'][$uid][$fileId]);
                $result['success'] = true;
            } else {
                $result['error'] = $modx->lexicon('ajaxupload.notFound', array('maxFiles' => $modx->ajaxupload->config['maxFiles']));
            }
        }
    } else {
        // Upload the image(s)
        $uploader = new qqFileUploader($modx->ajaxupload->config['allowedExtensions'], $modx->ajaxupload->config['sizeLimit']);
        // To pass data through iframe you will need to encode all html tags
        $result = $uploader->handleUpload($modx->ajaxupload->config['cachePath'], true, $modx->lexicon->fetch('ajaxupload.', true));

        // File successful uploaded
        if ($result['success']) {
            $fileInfo = array();
            $path = $uploader->path;
            // Check if count of uploaded files are below max file count
            if (count($_SESSION['ajaxupload'][$uid]) < $modx->ajaxupload->config['maxFiles']) {
                $fileInfo['originalBaseUrl'] = $modx->ajaxupload->config['cachePath'];
                $fileInfo['path'] = $path;
                $fileInfo['base_url'] = $modx->ajaxupload->config['cacheUrl'];

                // Create unique filename and set permissions
                $fileInfo['uniqueName'] = md5($uploader->filename . time()) . '.' . $uploader->extension;
                @rename($path . $uploader->filename . '.' . $uploader->extension, $path . $fileInfo['uniqueName']);
                $filePerm = (int)$modx->ajaxupload->config['newFilePermissions'];
                @chmod($path . $fileInfo['uniqueName'], octdec($filePerm));

                $fileInfo['originalName'] = $uploader->filename . '.' . $uploader->extension;

                // Create thumbnail
                $fileInfo['thumbName'] = $modx->ajaxupload->generateThumbnail($fileInfo);
                if ($fileInfo['thumbName']) {
                    // Fill session
                    $hash = hash('md5', serialize($fileInfo));
                    $_SESSION['ajaxupload'][$uid][$hash] = $fileInfo;
                    // Prepare returned values (filename, originalName & fileid)
                    $result['filename'] = $fileInfo['base_url'] . $fileInfo['thumbName'];
                    $result['originalName'] = $fileInfo['originalName'];
                    $result['fileid'] = $hash;
                } else {
                    unset($result['success']);
                    $result['error'] = $modx->lexicon('ajaxupload.thumbnailGenerationProblem');
                    @unlink($path . $fileInfo['uniqueName']);
                }
            } else {
                unset($result['success']);
                // Error message
                $result['error'] = $modx->lexicon('ajaxupload.maxFiles', array('maxFiles' => $modx->ajaxupload->config['maxFiles']));
                // Delete uploaded file
                @unlink($path . $uploader->filename . '.' . $uploader->extension);
            }
        }
    }
    $output = htmlspecialchars(json_encode($result), ENT_NOQUOTES);
}
return $output;