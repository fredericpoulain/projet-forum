
const preview = document.querySelector('.preview');
const formResponse = document.querySelector('.formResponse');
const inputResponse = document.querySelector('.inputResponse');
const alertMessageTopic = document.querySelector('.alertMessageTopic');

const allBtnsQuote = document.querySelectorAll('.quoteMessage')
const editor = document.querySelector('.ql-editor');


const toolbarOptions = {
    container: [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote'],
        [{ 'header': 1 }, { 'header': 2 }],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        // [{ 'script': 'sub' }, { 'script': 'super' }],
        // [{ 'indent': '-1' }, { 'indent': '+1' }],
        // [{ 'direction': 'rtl' }],
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
        [{ 'color': [] }, { 'background': [] }],
        // [{ 'font': [] }],
        // [{ 'align': [] }],
        ['clean'],
        ['emoji'],
        ['link', 'image', 'video']
    ],
    handlers: {
        'emoji': function () { }
    }
}

const quill = new Quill('.editor', {
    modules: {
        "toolbar": toolbarOptions,
        "emoji-toolbar": true,
        "emoji-shortname": true,
        "emoji-textarea": false
    },
    placeholder: 'Compose an epic...',
    theme: 'snow',
});

formResponse.addEventListener('submit', async (e) => {
    e.preventDefault();

    /**
     * !cas particulier
     * si le user clique sur "enter" sans rien écrire, Quill va quand même insérer du contenu : des balises vides. Donc la vérification if (quill.root.innerHTML === "") ne fonctionne pas.
     * l'astuce : 
     * l'astuce c'est de remplacer les balises vides' par rien du tout, sauf si la chaîne contient au moins une balise img. La méthode replace prend en premier argument une expression régulière qui correspond à toutes les balises HTML (c’est-à-dire tout ce qui est entre < et >), et en second argument une fonction de callback (ou fléchée plus loin) qui renvoie la correspondance elle-même si elle contient la chaîne 'img', sinon une chaîne vide.
     * 
     * Autre version plus "lisible" :
     * Dans cet exemple, nous utilisons une fonction de rappel pour vérifier si chaque correspondance de la regex contient la chaîne 'img'. Si c’est le cas, nous renvoyons la correspondance elle-même (c’est-à-dire que nous ne remplaçons rien), sinon nous renvoyons une chaîne vide pour remplacer la balise par rien du tout :
     *  
     * const contenuDeVerification = quill.root.innerHTML.replace(/<[^>]*>/g, function(match) {
        if (match.includes('img')) {
            return match;
        } else {
            return '';
        }
    });
     */
    const contenuDeVerification = quill.root.innerHTML.replace(/<[^>]*>/g, match => match.includes('img') ? match : '');
    // si la chaine n'est pas vide
    if (contenuDeVerification) {
        //tout le contenu de l'editeur Quill est enregistré dans le champs "inputResponse"
        inputResponse.value = quill.root.innerHTML;
        // parcourir le contenu de l'éditeur pour trouver les images encodées en base64
        const parser = new DOMParser();
        const doc = parser.parseFromString(inputResponse.value, 'text/html');
        const images = doc.querySelectorAll('img[src^="data:image/"]');
        try {
            //on boucle sur toutes les images pour s'assurer que le message du User ne comporte pas d'image supérieur à 300ko
            for (const image of images) {
                const imageBase64 = image.src;
                const blob = await fetch(imageBase64).then(res => res.blob());
                if (blob.size > 307200) {
                    throw new Error(`Le poids de l'image doit être inférieure à 300ko`);
                }
            }
            // si aucunes images n'est supérieur à 300ko le code continue...
            for (const image of images) {
                const imageBase64 = image.src;
                //*la fonction uploadImage renvoi l'adresse de l'image stocker sur le serveur.
                const imageUrl = await uploadImage(imageBase64);
                // * Une fois l'url récupérer, on remplace la représentation en base64 par l'URL de l'image.
                //! ainsi le contenu de l'editeur qui sera envoyer en base de données ne va pas contenir le text + image base64 mais bien le text + les urls d'images
                image.src = imageUrl;

            }
            inputResponse.value = doc.body.innerHTML;
            const formData = new FormData(formResponse);
            const response = await fetch('?controller=topics&action=validation', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                //si erreur detecté, on génère un nouveau message d'erreur.
                // le "catch" va le récupérer.
                throw new Error(`Une erreur est survenue: ${response.status}`);
            }

            const resultat = await response.json();

            //si le boolen est à false
            if (!resultat.boolean) {
                throw new Error(resultat.message);
            }
            //Si true, on met à jour le DOM
            updateDOM(resultat);


        } catch (error) {

            if (error.message === 'noConnected') {
                //je le redirige en haut de la page pour qu'il se conecte, ensuite il sera redirigé vers la zone d'édition (voir code dans app.js)
                window.scrollTo({ top: 0, behavior: 'smooth' });
                document.querySelector('.btnLogin').classList.add('btnLoginAnimate')
            } else if (error.message === 'expired token') {
                window.location = "?controller=login";
            } else {
                alertMessageTopic.textContent = error.message;
                alertMessageTopic.style.display = "block";
            }
        }
    } else {
        alertMessageTopic.textContent = "Veuillez entrer du contenu avant de poster votre réponse";
        alertMessageTopic.style.display = "block";
    }



});
async function uploadImage(imageBase64) {
    // * imageBase64 correspond à l'image en base64.
    // * la ligne ci dessous converti la représentation en base64 de l’image en un objet Blob.
    //* Blob contient les données binaires brutes de l’image
    const blob = await fetch(imageBase64).then(res => res.blob());

    if (blob.size > 307200) {
        throw new Error(`Le poids de l'image doit être inférieure à 300ko`);
    }
    const topicID = document.querySelector('.topicID').value;
    // *créer un objet FormData pour envoyer les données de l'image au serveur via la constante "blob"
    const formData = new FormData();
    formData.append('image', blob);
    formData.append('topicID', topicID);

    // * envoi d'une requête POST au serveur avec les données de l'image. Ce dernier l'interpretera avec un $_file
    // try {
    const response = await fetch('?controller=topics&action=uploadImage', {
        method: 'POST',
        body: formData,
    });

    // vérifier si la requête a réussi
    if (!response.ok) {
        throw new Error(`Une erreur est survenue lors du téléchargement de l'image: ${response.status}`);
    }

    //si la communication c'est bien déroulée , on traite les donnée json
    const resultat = await response.json();

    if (!resultat.boolean) {
        // dataTypeError = resultat.data;
        throw new Error(resultat.message);
    }

    return resultat.data.url



}

/**
 * !écouteur sur quill pour supprimer les éventuels message d'alerte
 * 
 * !On ne peux pas utiliser addEventListener directement avec l’éditeur Quill. L’éditeur Quill est une instance d’un objet JavaScript qui fournit sa propre API pour gérer les événements. Pour ajouter un écouteur d’événement à l’éditeur Quill, on doit utiliser la méthode "on" de l’éditeur Quill,
 */

// Ajouter un écouteur d'événement pour l'événement "text-change" de l'éditeur Quill
quill.on('text-change', () => {
    // Vérifier si du texte a été entré dans l'éditeur
    if (quill.getText()) {
        //supprime les éventuels messages d'alerte
        alertMessageTopic.textContent = "";
        alertMessageTopic.style.display = "none";
    }
});







allBtnsQuote.forEach(btnQuote => {
    btnQuote.addEventListener('click', () => {
        //dans chaque message listé, il y a deux attributs, data-pseudo et data-date qui possèdent du pseudo et de la date du message. cela permet de donner des infos à la citation. 
        const pseudoMessageCite = btnQuote.getAttribute('data-pseudo')
        const dateMessageCite = btnQuote.getAttribute('data-date')
        const messageQuote = btnQuote.parentNode.nextElementSibling.innerHTML;
        //Quill ne prend en compte que quelques balises html
        const insertDatas = `<blockquote><em><u>${pseudoMessageCite}</u> a écrit le ${dateMessageCite} : </em><br>${messageQuote}</blockquote>`;

        //*Cette ligne utilise la méthode dangerouslyPasteHTML de l’objet clipboard de Quill pour insérer du contenu HTML dans l’éditeur Quill. Le premier argument, quill.getLength(), spécifie l’index où le contenu HTML doit être inséré. Dans ce cas, nous utilisons la méthode getLength pour obtenir la longueur actuelle du contenu de l’éditeur, ce qui signifie que le contenu HTML sera inséré à la fin du contenu existant. Le deuxième argument, insertDatas, est le contenu HTML à insérer.
        quill.clipboard.dangerouslyPasteHTML(quill.getLength(), insertDatas);

        //*Cette ligne utilise la méthode insertText pour insérer un caractère de saut de ligne (\n) dans l’éditeur Quill. Le premier argument, quill.getLength(), spécifie l’index où le caractère de saut de ligne doit être inséré. Comme pour la ligne précédente, nous utilisons la méthode getLength pour obtenir la longueur actuelle du contenu de l’éditeur, ce qui signifie que le caractère de saut de ligne sera inséré à la fin du contenu existant. Le deuxième argument, '\n', est le caractère de saut de ligne à insérer.
        quill.insertText(quill.getLength(), '\n');

        //*Cette ligne utilise la méthode setSelection pour déplacer le curseur dans l’éditeur Quill. Le premier argument, quill.getLength(), spécifie l’index où le curseur doit être placé. Comme pour les lignes précédentes, nous utilisons la méthode getLength pour obtenir la longueur actuelle du contenu de l’éditeur, ce qui signifie que le curseur sera placé à la fin du contenu existant. Le deuxième argument, 0, spécifie la longueur de la sélection. Dans ce cas, nous passons 0 pour indiquer que nous ne voulons pas sélectionner de texte, mais simplement déplacer le curseur.
        quill.setSelection(quill.getLength(), 0);
    })
});




const formatDate = (date) => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: '2-digit', hour: '2-digit', minute: '2-digit' };
    let dateString = date.toLocaleString('fr-FR', options);
    dateString = dateString.charAt(0).toUpperCase() + dateString.slice(1);
    dateString = dateString.replace(':', 'h');
    return dateString;
}


function updateDOM(resultat) {
    const filePathAvatar = resultat.data.dataUser.filepathAvatar;
    const pseudo = resultat.data.dataUser.pseudo;
    const userGuitare = resultat.data.dataUser.userGuitare;
    const messagesCount = Number(resultat.data.dataUser.messagesCount) + 1;

    const templateItem = document.querySelector('.template-item');
    const messageList = document.querySelector('.messageList');
    const lastMessage = templateItem.content.cloneNode(true);

    lastMessage.querySelector('.avatar img').setAttribute('src', `/images/profils/${filePathAvatar}`);
    lastMessage.querySelector('.pseudo span').textContent = pseudo;
    lastMessage.querySelector('.guitare span').textContent = 'Ma guitare: ' + userGuitare;
    lastMessage.querySelector('.totalMessagesUser span').textContent = messagesCount + ' message' + (messagesCount > 1 ? 's' : '');

    let date = new Date();
    const dateActuelle = formatDate(date);

    lastMessage.querySelector('.messageDate span').textContent = dateActuelle;
    lastMessage.querySelector('.quoteMessage').setAttribute('data-pseudo', pseudo);
    lastMessage.querySelector('.quoteMessage').setAttribute('data-date', dateActuelle);
    lastMessage.querySelector('.messageText span').innerHTML = resultat.data.reponseTopic;

    messageList.append(lastMessage);
    quill.root.innerHTML = "";
}