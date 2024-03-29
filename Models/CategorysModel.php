<?php

namespace Models;

use Entities\Categorys;
use PDO;
use Exception;
use Core\DbConnect;


class CategorysModel extends DbConnect
{

    public function getAllWithParent()
    {
        $req = "
        SELECT p.categoryName AS categoryName,
               p.categoryID AS categoryID,
               c.categoryName AS parentCategoryName,
               c.categoryID AS parentCategoryID
        FROM categorys c
        RIGHT JOIN categorys p ON c.categoryID = p.categoryParentID
        ";
        $sql = $this->getBdd()->prepare($req);
        try {
            $sql->execute();
            $resultat = $sql->fetchAll();
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    public function getCategorysList()
    {
        // $req = "SELECT 
        // c1.categoryName AS category, 
        // c2.categoryName AS subcategory, 
        // c2.categoryDescription AS categoryDescription, 
        // c2.categoryID AS categoryID,
        // c2.categorySlug AS categorySlug,

        // COUNT(DISTINCT t.topicId) AS topicCount,
        // COUNT(DISTINCT r.responseId) AS responseCount,
        // GREATEST(
        //     COALESCE(MAX(t.topicDate), 0),
        //     COALESCE(MAX(r.responseDate), 0)
        // ) AS lastActivityDate,
        // CASE
        //     WHEN MAX(r.responseDate) IS NULL OR MAX(t.topicDate) >= MAX(r.responseDate) THEN ut.pseudo
        //     ELSE ur.pseudo
        // END AS lastActivityUser,
        // (SELECT topicTitle FROM topics WHERE categoryID = c2.categoryID ORDER BY topicDate DESC LIMIT 1) AS lastTopicTitle
        // FROM categorys c1

        // JOIN categorys c2 ON c1.categoryID = c2.categoryParentID
        // LEFT JOIN topics t ON c2.categoryID = t.categoryID
        // LEFT JOIN responses r ON t.topicId = r.topicID
        // LEFT JOIN users ut ON t.userID = ut.userID
        // LEFT JOIN users ur ON r.userID = ur.userID
        // GROUP BY c1.categoryName, c2.categoryName
        // ORDER BY c1.categoryName;

        // ";



        // $req = "SELECT c1.categoryName AS category, c2.categoryName AS subcategory, c2.categoryDescription AS categoryDescription, COUNT(DISTINCT t.topicId) AS topicCount, COUNT(DISTINCT r.responseId) AS responseCount
        // FROM categorys c1
        // JOIN categorys c2 ON c1.categoryID = c2.categoryParentID
        // LEFT JOIN topics t ON c2.categoryID = t.categoryID
        // LEFT JOIN responses r ON t.topicId = r.topicID
        // GROUP BY c1.categoryName, c2.categoryName
        // ORDER BY c1.categoryName;";


        // $req = "SELECT c1.categoryName AS category, c2.categoryName AS subcategory, c2.categoryDescription AS categoryDescription FROM Categorys c1 JOIN Categorys c2 ON c1.categoryID = c2.CategoryParentID ORDER BY c1.categoryName";

        //! Obligé d'ajouter le mot clé "DISTINCT" à la requête, sinon le nombre total de topics poourrait être faux. Cela peut se produire si plusieurs lignes de la table messages sont associées au même sujet. Dans ce cas, la jointure entre les tables topics et messages renverra plusieurs lignes pour le même sujet, ce qui peut fausser les résultats de la fonction d’agrégation COUNT().

        //!L’utilisation de DISTINCT dans la fonction d’agrégation COUNT() permet de compter uniquement les valeurs distinctes de la colonne topicID, ce qui élimine les doublons et renvoie le nombre correct de sujets pour chaque sous-catégorie.

        //! j'aurais pu utiliser une sous-requêtes : 
        //*(SELECT COUNT(*) FROM topics t WHERE t.categoryID = c.categoryID) AS totalTopics
        //! MAIS l’utilisation de sous-requêtes peut affecter les performances de la requête en fonction de la taille de votre base de données.

// c.categorySlug AS subCategorySlug,
        $req = "SELECT 
            p.categoryName AS parentCategoryName,
            p.categoryID AS parentCategoryID,
            c.categoryName AS subCategoryName,
            c.categoryID AS subCategoryID,           
            c.categoryDescription AS subCategoryDesc,
            COUNT(DISTINCT t.topicID) AS totalTopics,
            COUNT(m.messageID) AS totalMessages,
            (SELECT t2.topicTitle FROM messages m2 JOIN topics t2 ON m2.topicID = t2.topicID WHERE m2.messageID = MAX(m.messageID)) AS lastTopicTitle,
            MAX(m.messageDate) AS lastMessageDate,
            (SELECT u.pseudo FROM messages m2 JOIN users u ON m2.userID = u.userID WHERE m2.messageID = MAX(m.messageID)) AS lastMessageUser
        FROM categorys p
        LEFT JOIN categorys c ON p.categoryID = c.categoryParentID
        LEFT JOIN topics t ON c.categoryID = t.categoryID
        LEFT JOIN messages m ON t.topicID = m.topicID
        WHERE p.categoryParentID IS NULL
        GROUP BY p.categoryID, c.categoryID        
        ";

        $sql = $this->getBdd()->prepare($req);
        try {
            $sql->execute();
            $resultat = $sql->fetchAll();
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }

    //!2
    // public function getCategorysList()
    // {
    //     $req = "SELECT c1.categoryName AS category, c2.categoryName AS subcategory
    //     FROM Categorys c1
    //     INNER JOIN Categorys c2
    //     ON c1.categoryID = c2.CategoryParentID
    //     ORDER BY c1.categoryName";
    //     $sql = $this->getBdd()->prepare($req);
    //     try {
    //         $sql->execute();
    //         $resultat = $sql->fetchAll();
    //         $sql->closeCursor();
    //         return $resultat;
    //     } catch (Exception $e) {
    //         die('Erreur : ' . $e->getMessage());
    //     }
    // }


    // public function getCategorysListWithRECURSIVE()
    // {
    //     //! Requête récursive avec une expression de table commune (CTE) : 
    //     // Cette requête utilise une expression de table commune récursive pour construire la hiérarchie des catégories. 
    //     //La première partie de l’expression WITH RECURSIVE sélectionne toutes les catégories parentes (c’est-à-dire celles dont CategoryParentID est NULL) et leur attribue un niveau 1. 
    //     //La deuxième partie de l’expression utilise l’opérateur UNION ALL pour ajouter récursivement les sous-catégories à la hiérarchie en joignant la table Categorys à elle-même en utilisant la colonne CategoryParentID. Le niveau des sous-catégories est incrémenté à chaque itération. 
    //     //La requête finale sélectionne toutes les lignes de l’expression de table commune et renvoie les résultats.

    //     //le resultat sera trié grace à la méthode static buildCategoryHierarchy() présent dans la toolbox, depuis le VisiteurControleur dans la méthode accueil() 

    //     $req = "WITH RECURSIVE CategoryHierarchy AS (
    //         SELECT categoryID, categoryName, CategoryParentID, 1 as level
    //         FROM Categorys
    //         WHERE CategoryParentID IS NULL
    //         UNION ALL
    //         SELECT c.categoryID, c.categoryName, c.CategoryParentID, ch.level + 1
    //         FROM Categorys c
    //         JOIN CategoryHierarchy ch
    //         ON c.CategoryParentID = ch.categoryID
    //     )
    //     SELECT * FROM CategoryHierarchy";


    //     $sql = $this->getBdd()->prepare($req);
    //     try {
    //         $sql->execute();
    //         $resultat = $sql->fetchAll();
    //         $sql->closeCursor();
    //         return $resultat;
    //     } catch (Exception $e) {
    //         die('Erreur : ' . $e->getMessage());
    //     }
    // }

    public function getCategorysOrderByParent()
    {
        $req = "SELECT * FROM categorys ORDER BY COALESCE(categoryParentID, categoryID), categoryParentID IS NOT NULL, categoryID";
        $sql = $this->getBdd()->prepare($req);
        try {
            $sql->execute();
            $resultat = $sql->fetchAll();
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }

    public function getDetailsParentCat($parentID)
    {
        $req = "SELECT 
        p.categoryID AS ParentID,
        p.categoryName AS parentCategoryName,
        c.categoryName AS name,
        c.categoryDescription AS description,
        c.categoryID AS id,
        CONCAT('?controller=topics&action=list&catID=', c.categoryID) AS url,
        COUNT(DISTINCT t.topicID) AS totalTopics,
        COUNT(m.messageID) AS totalMessages,
        (SELECT t2.topicTitle FROM messages m2 JOIN topics t2 ON m2.topicID = t2.topicID WHERE m2.messageID = MAX(m.messageID)) AS lastTopicTitle,
        MAX(m.messageDate) AS lastMessageDate,
        (SELECT u.pseudo FROM messages m2 JOIN users u ON m2.userID = u.userID WHERE m2.messageID = MAX(m.messageID)) AS lastMessageUser
        FROM categorys p
        JOIN categorys c ON p.categoryID = c.categoryParentID
        JOIN topics t ON c.categoryID = t.categoryID
        JOIN messages m ON t.topicID = m.topicID
        WHERE p.categoryID = :parentID
        GROUP BY c.categoryID    
          
    ";

        $sql = $this->getBdd()->prepare($req);
        try {
            $sql->bindValue(":parentID", $parentID);
            $sql->execute();
            $resultat = $sql->fetchAll();
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    public function getInfoCategory($categoryID)
    {
        //la requete est executée seulement pour les sous catégories, son elles auront toujours un parent. le LEFT est donc optionnel.
        $req = "SELECT categorys.*, parent.categoryName AS categoryParentName    
        FROM categorys
        LEFT JOIN categorys parent ON categorys.categoryParentID = parent.categoryID
        WHERE categorys.categoryID = :categoryID";

        $sql = $this->getBdd()->prepare($req);
        try {
            $sql->bindValue(":categoryID", $categoryID);
            $sql->execute();
            $resultat = $sql->fetch();
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    // public function getInfoCategory($categoryID)
    // {
    //     $req = "SELECT * FROM categorys WHERE categoryID = :categoryID";
    //     $sql = $this->getBdd()->prepare($req);
    //     $sql->bindValue(":categoryID", $categoryID);
    //     try {
    //         $sql->execute();
    //         $resultat = $sql->fetch();
    //         $sql->closeCursor();
    //         return $resultat;
    //     } catch (Exception $e) {
    //         die('Erreur : ' . $e->getMessage());
    //     }
    // }

    public function getParentCategory()
    {
        $req = "SELECT * FROM categorys WHERE categoryParentID IS null";
        $sql = $this->getBdd()->prepare($req);
        try {
            $sql->execute();
            $resultat = $sql->fetchAll();
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    public function categoryHasChildren($categoryID)
    {
        $req = "SELECT COUNT(*)  as countChildren
                FROM categorys 
                WHERE categoryParentID = :categoryID";
        $sql = $this->getBdd()->prepare($req);
        try {
            $sql->bindValue(":categoryID", $categoryID);
            $sql->execute();
            $resultat = $sql->fetch();
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    public function addCategory(Categorys $categorys)
    {
        $categoryName = $categorys->getCategoryName();
        $categoryDescription = $categorys->getCategoryDescription();
        $categoryParentID  = $categorys->getCategoryIdParent();

        $req = "INSERT INTO categorys (categoryName, categoryDescription, categoryParentID ) VALUES (:categoryName, :categoryDescription, :categoryParentID)";
        $sql = $this->getBdd()->prepare($req);
        $sql->bindValue(":categoryName", $categoryName);
        $sql->bindValue(":categoryDescription", $categoryDescription);
        $sql->bindValue(":categoryParentID", $categoryParentID);
        try {
            $sql->execute();
            $resultat = ($sql->rowCount() > 0);
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    public function editCategory(Categorys $categorys)
    {

        $categoryID = $categorys->getCategoryId();
        $categoryName = $categorys->getCategoryName();
        $categoryDescription = $categorys->getCategoryDescription();
        $categoryParentID  = $categorys->getCategoryIdParent();

        $req = "UPDATE categorys set 
                     categoryName = :categoryName , 
                     categoryDescription = :categoryDescription,
                     categoryParentID = :categoryParentID  
                 WHERE categoryID = :categoryID";
        $sql = $this->getBdd()->prepare($req);
        $sql->bindValue(":categoryID", $categoryID, \PDO::PARAM_INT);
        $sql->bindValue(":categoryName", $categoryName);
        $sql->bindValue(":categoryDescription", $categoryDescription);
        $sql->bindValue(":categoryParentID", $categoryParentID);
        try {
            $sql->execute();
            $resultat = ($sql->rowCount() > 0);
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
    public function deleteCategory($id)
    {

        $req = "DELETE FROM categorys WHERE categoryID = :categoryID";
        $sql = $this->getBdd()->prepare($req);
        $sql->bindValue(":categoryID", $id, PDO::PARAM_INT);
        try {
            $sql->execute();
            $resultat = ($sql->rowCount() > 0);
            $sql->closeCursor();
            return $resultat;
        } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
        }
    }
}

// $stmt = $pdo->prepare($sql);
// $stmt->execute();
// $results = $stmt->fetchAll(PDO::FETCH_ASSOC);