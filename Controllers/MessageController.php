<?php

namespace Controllers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Entities\Topics;
use Entities\Messages;
use Models\TopicsModel;
use Models\MessagesModel;
use Models\CategorysModel;
use Controllers\MainController;
use Controllers\Services\Toolbox;
use Controllers\Services\Securite;
use Controllers\Traits\VerifPostTrait;


class MessageController extends MainController
{
    use VerifPostTrait;
    private CategorysModel $categorysModel;
    private TopicsModel $topicsModel;
    private MessagesModel $messagesModel;
    private Messages $message;
    private Topics $topic;


    public function __construct()
    {
        $this->categorysModel = new CategorysModel();
        $this->topicsModel = new TopicsModel();
        $this->messagesModel = new MessagesModel();
        $this->message = new Messages;
        $this->topic = new Topics;;
    }


    /**
     * Enregistre une image en tant que "fichier" sur le serveur
     *
     * @return void
     */
    public function uploadImage($messageID)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0 && !empty($_POST['topicID'])) {
                $datasImage = $_FILES['image'];
                $topicID = htmlspecialchars($_POST['topicID']);
                //je crée un tableau avec les types mime autorisés
                $typeMime = [
                    "jpg" => "image/jpg",
                    "jpeg" => "image/jpeg",
                    "gif" => "image/gif",
                    "png" => "image/png"
                ];


                //on verifie le type mime du fichier
                if (!in_array($datasImage['type'], $typeMime)) {
                    Toolbox::dataJson(false, "Le fichier n'est pas une image valide. Extensions autorisées : png, gif ou jpeg(jpg)", $topicID);
                    exit;
                }

                //on vérifie sa taille
                if ($datasImage['size'] > 307200) {
                    Toolbox::dataJson(false, "Le poids de l'image doit être inférieure à 300ko");
                    exit;
                }


                $filePath = './images/topics/' . $topicID . '/' . $messageID;
                // $filePath = 'images/topics/' . $topicID;
                //Si ce dossier n'existe pas, il faut le créer
                if (!file_exists($filePath)) {
                    mkdir($filePath, 0777, true);
                }

                // $datasImage['type'] = image/xxx
                // $array = explode("/", $datasImage['type'])[1];
                $extension = explode("/", $datasImage['type'])[1];
                $imageRename = uniqid($topicID, true) . '.' . $extension;

                //on déplace l'image des "temporaire" dans le dossier du user
                $moveImage = move_uploaded_file($datasImage['tmp_name'], $filePath . '/' . $imageRename);
                if ($moveImage) {

                    $imageURL = $filePath . '/' . $imageRename;
                    // $imageURL = './' . $filePath . '/' . $imageRename;
                    $dataImage = [
                        'url' => $imageURL
                    ];
                    Toolbox::dataJson(true, "Image enregistrée avec succès", $dataImage);
                    exit;
                } else {
                    Toolbox::dataJson(false, "Problème rencontré lors de l'enregistrement");
                    exit;
                }
            } else {
                Toolbox::dataJson(false, "Erreur d'upload d'image", $_POST);
                exit;
            }
        } else {
            header("Location:index.php");
            exit;
        }
    }

    /**
     * Valide et enregistre le message
     *
     * @return void
     */
    public function create()
    {
        if ($this->VerifPostTrait()) {
            if (!empty($_POST['inputMessage']) && !empty($_POST['topicID'])) {

                /**
                 * !on fait la même vérif ici que celle de JS pour les réponses vides :
                 * Voir explication détaillée dans le cas particulier du fichier createMessage.js
                 */


                if ($this->verifContent($_POST['inputMessage'])) {

                    $topicID = htmlspecialchars($_POST['topicID']);
                    //On nettoie l'html
                    $cleanHTML = Securite::htmlPurifier($_POST['inputMessage']);
                    // Supprimez les balises <p><br></p> vides

                    $cleanHTML = Securite::removeEmptyTags($cleanHTML);

//                    $cleanHTML = str_replace('&iuml;&raquo;&iquest;', '', $cleanHTML);
                    $cleanHTML = Securite::deleteQlCursorQuill($cleanHTML);

                    $this->message->setMessageText($cleanHTML);
                    $this->message->setUserID($_SESSION['profil']['userID']);
                    $this->message->setTopicID($topicID);

                    //je fais appel à mon messageModel pour enregistrer les données en lui injectant "$this->message" qui correspond à l'instance de "new Message()" :
                    if ($this->messagesModel->createMessage($this->message)) {

                        $messageID = $this->messagesModel->lastInsertId();
                        $data = [
                            'reponseTopic' => $cleanHTML,
                            'messageID' => $messageID,
                            // la catégorie sert uniquemment pour la redirection du script CreateTopic.js
                            'categoryID' => isset($_POST['categoryID']) ? htmlspecialchars($_POST['categoryID']) : "",
                            'dataUser' => $_SESSION['profil'],
                        ];
                        $_SESSION['profil']['messagesCount']++;

                        //si cette variable est déclarée c'est la création d'un topic.
                        if (isset($_POST['titleTopic'])) {
                            Toolbox::ajouterMessageAlerte('Topic créé avec succès', 'vert');
                        }
                        //et on envoie la réponse en json
                        Toolbox::dataJson(true, "données reçues, ok !", $data);
                        exit;
                    } else {
                        Toolbox::dataJson(false, "Une erreur s'est produite");
                        exit;
                    }
                } else {
                    Toolbox::dataJson(false, "PHP : Veuillez entrer du contenu avant de poster votre réponse", $_POST['inputMessage']);
                    exit;
                }
            } else {
                Toolbox::dataJson(false, "Erreur transmission POST inputMessage");
                exit;
            }
        }
    }
    /**
     * Valide et enregistre le message
     *
     * @return void
     */
    public function update($messageID)
    {


        if ($this->VerifPostTrait()) {
            if (!empty($_POST['inputMessage'])) {

                /**
                 * !on fait la même vérif ici que celle de JS pour les réponses vides :
                 * Voir explication détaillée dans le cas particulier du fichier createMessage.js
                 */

                if ($this->verifContent($_POST['inputMessage'])) {

                    //On nettoie l'html
                    $cleanHTML = Securite::htmlPurifier($_POST['inputMessage']);
//                    $cleanHTML = $this->supprimerBalisesVides($cleanHTML);
                    $cleanHTML = Securite::removeEmptyTags($cleanHTML);
                    $cleanHTML = Securite::deleteQlCursorQuill($cleanHTML);
//                    $cleanHTML = str_replace(array('&iuml;&raquo;&iquest;', '<span class="ql-cursor"></span>'), '', $cleanHTML);
                    $infoMessage = $this->messagesModel->getInfoMessage($messageID);

                    $messageInBDD = $infoMessage->messageText;
                    $topicID = $infoMessage->messageTopicID;

                    //on supprime les éventuelles images que le user a supprimées de son message
                    $this->deleteImage($messageInBDD, $cleanHTML);


                    $this->message->setMessageText($cleanHTML);

                    $this->message->setMessageID($messageID);

                    //je fais appel à mon messageModel pour EDITER les données:

                    if (
                        $infoMessage->messageUserID === $_SESSION['profil']['userID'] &&
                        $this->messagesModel->editMessage($this->message)
                    ) {
                        $data = [
                            'reponseTopic' => $cleanHTML,
                            'topicID' => $topicID,
                            'messageID' => $messageID,
                            'categoryID' => isset($_POST['categoryID']) ? htmlspecialchars($_POST['categoryID']) : "",
                            'dataUser' => $_SESSION['profil'],
                        ];
                        /**
                         * Pas de message en session dans le cas d'une création car l'ajout se fait en ajax sans redirection. Le message s'affichera via JS dans le DOM 
                         */
                        if (isset($_POST['action'])) {
                            switch ($_POST['action']) {
                                case 'createTopic':
                                    Toolbox::ajouterMessageAlerte('Topic créé avec succès', 'vert');
                                    break;
                                case 'updateMessage':
                                    Toolbox::ajouterMessageAlerte('Message modifié avec succès', 'vert');
                                    break;
                            }
                        }
                        //et on envoie la réponse en json
                        Toolbox::dataJson(true, "données reçues, ok !", $data);
                        exit;
                    } else {
                        Toolbox::dataJson(false, "Une erreur s'est produite !");
                        exit;
                    }
                } else {
                    Toolbox::dataJson(false, "PHP : Veuillez entrer du contenu avant de poster votre réponse", $_POST['inputMessage']);
                    exit;
                }
            } else {
                Toolbox::dataJson(false, "PHP : Erreur transmission POST inputMessage");
                exit;
            }
        }
    }

    private function deleteImage($messageInBDD, $cleanHTML): void
    {
        preg_match_all('/<img[^>]+src="([^">]+)"/', $messageInBDD, $tab1);
        preg_match_all('/<img[^>]+src="([^">]+)"/', $cleanHTML, $tab2);
        /**
         * $tab[0] = tableau contenant les balises img
         * $tab[1] = tableau contenant les attributs src de chaque 'img'
         */
        $imagesOldMsg = $tab1[1];
        $imagesNewMsg = $tab2[1];

        //je boucle sur imagesOldMsg, et pour chaque itération je vérifie si le contenu de l'index de imagesOldMsg se trouve dans le tableau imagesNewMsg. Si c'est le cas, l'image n'a pas été supprimée. Si elle n'est pas présente, on peux supprimer cette image du serveur.

        foreach ($imagesOldMsg as $image) {
            if (!in_array($image, $imagesNewMsg, true) && file_exists($image)) {
                unlink($image);
            }
        }
    }

    private function verifContent($content)
    {
        return preg_replace_callback('/<[^>]*>/', function ($match) {
            if (strpos($match[0], 'img') !== false) {
                return $match[0];
            } else {
                return '';
            }
        }, $content);
    }
    private function supprimerBalisesVides($html) {

        // Créez un nouveau document DOM
        $dom = new DOMDocument;

        // Ajoutez une balise <div> autour de votre HTML pour pouvoir le charger dans le DOM
        $dom->loadHTML('<div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Obtenez la première balise <div>
        $div = $dom->getElementsByTagName('div')->item(0);

        // Supprimez tous les espaces blancs et les nouvelles lignes entre les balises <p> et </p>
        foreach ($div->getElementsByTagName('p') as $p) {
            if ($p->firstChild && $p->firstChild->nodeType === XML_TEXT_NODE && trim($p->firstChild->textContent) === '') {
                $p->removeChild($p->firstChild);
            }
        }

        // Supprimez les balises <p><br></p> et <p></p> vides au début
        while ($div->firstChild && $div->firstChild->nodeName === 'p' && ($div->firstChild->childNodes->length === 0 || ($div->firstChild->childNodes->length === 1 && $div->firstChild->firstChild->nodeName === 'br'))) {
            $div->removeChild($div->firstChild);
        }

        // Supprimez les balises <p><br></p> et <p></p> vides à la fin
        while ($div->lastChild && $div->lastChild->nodeName === 'p' && ($div->lastChild->childNodes->length === 0 || ($div->lastChild->childNodes->length === 1 && $div->lastChild->firstChild->nodeName === 'br'))) {
            $div->removeChild($div->lastChild);
        }

        // Retournez le HTML modifié sans la balise <div> extérieure
        $html = str_replace(array('<div>', '</div>'), '', $dom->saveHTML());

        return trim($html);
    }

    public function viewEdit($messageID)
    {
        dd($messageID);
        $infoMessage = $this->messagesModel->getInfoMessage($messageID);
        // dump($infoMessage);
        if (Securite::isConnected() && $infoMessage->messageUserID === $_SESSION['profil']['userID']) {
            $data_page = [
                "pageDescription" => "Page d'édition d'un message' sur Guitare-forum",
                "pageTitle" => "Modifier son message | Guitare-forum",
                "view" => "../Views/messages/viewEditMessage.php",
                "css" => "./style/createTopicStyle.css",
                "script" => "./js/updateMessage.js",
                "template" => "../Views/common/template.php",
                //editor quill
                "quillSnowCSS" => "//cdn.quilljs.com/1.3.6/quill.snow.css",
                "quillEmojiCSS" => "./quill/dist/quill-emoji.css",
                "quillJS" => "//cdn.quilljs.com/1.3.6/quill.js",
                "quillEmojiJS" => "./quill/dist/quill-emoji.js",
                "quillImageJS" => "./quill/dist/quill.imageUploader.js",
                "quillImageCSS" => "./quill/dist/quill.imageUploader.css",
                //----------
                "infoMessage" => $infoMessage

            ];
            $this->render($data_page);
        } else {
            header("Location:index.php?controller=login");
            exit;
        }
    }
}
