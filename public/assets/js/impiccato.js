const wordElement = document.getElementById("word");
const wrongLettersElement = document.getElementById("wrong-letters");
const playAgainButton = document.getElementById("play-button");
const popup = document.getElementById("popup-container");
const notification = document.getElementById("notification-container");
const finalMessage = document.getElementById("final-message");
const finalMessageRevealWord = document.getElementById(
    "final-message-reveal-word"
);
const figureParts = document.querySelectorAll(".figure-part");

const words = [
    "i pilastri della terra",
    "l albero delle bugie",
    "una ragazza senza ricordi",
    "l ultimo giorno di un condannato",
    "la biblioteca perduta",
    "l uomo che scambio sua moglie per un cappello",
    "una vita come tante",
    "l ultimo segreto",
    "sotto mentite spoglie",
    "normal neople",
    "the let them theory",
    "humankind",
    "careless people",
    "mandorla amara",
    "la levatrice",
    "i burger di ciccio",
    "un giorno questo dolore ti sara utile",
    "se i gatti scomparissero dal mondo",
    "la bussola d'oro"
];
let selectedWord = words[Math.floor(Math.random() * words.length)];

let playable = true;

const correctLetters = [];
const wrongLetters = [];

function displayWord() {
    wordElement.innerHTML = `
        ${selectedWord
        .split("")
        .map(
            (letter) => `
                    <span class="letter">
                       ${letter === " " ? "-" : correctLetters.includes(letter) ? letter : ""}
                    </span>
                `
        )
        .join("")}
    `;

    // rimuove gli spazi per il confronto
    let innerWord = wordElement.innerText.replace(/\n/g, "").replace(/ /g, "").replace(/-/g, "");
    let targetWord = selectedWord.replace(/ /g, "");

    if (innerWord === targetWord) {
        finalMessage.innerText = "Congratulazioni! Hai vinto!";
        finalMessageRevealWord.innerText = "";
        popup.style.display = "flex";
        playable = false;
        //query inserimento statistiche_gioco
    }

}


function updateWrongLettersElement() {
    wrongLettersElement.innerHTML = `
  ${wrongLetters.length > 0 ? "<p>Lettere sbagliate</p>" : ""}
  ${wrongLetters.map((letter) => `<span>${letter}</span>`)}
  `;
    figureParts.forEach((part, index) => {
        const errors = wrongLetters.length;
        index < errors
            ? (part.style.display = "block")
            : (part.style.display = "none");
    });
    if (wrongLetters.length === figureParts.length) {
        finalMessage.innerText = "Hai perso!";
        finalMessageRevealWord.innerText = `...il titolo era: ${selectedWord}`;
        popup.style.display = "flex";
        playable = false;
    }
}

function showNotification() {
    notification.classList.add("show");
    setTimeout(() => {
        notification.classList.remove("show");
    }, 2000);
}

window.addEventListener("keypress", (e) => {
    if (playable) {
        const letter = e.key.toLowerCase();
        if (letter >= "a" && letter <= "z") {
            if (selectedWord.includes(letter)) {
                if (!correctLetters.includes(letter)) {
                    correctLetters.push(letter);
                    displayWord();
                } else {
                    showNotification();
                }
            } else {
                if (!wrongLetters.includes(letter)) {
                    wrongLetters.push(letter);
                    updateWrongLettersElement();
                } else {
                    showNotification();
                }
            }
        }
    }
});

playAgainButton.addEventListener("click", () => {
    playable = true;
    correctLetters.splice(0);
    wrongLetters.splice(0);
    selectedWord = words[Math.floor(Math.random() * words.length)];
    displayWord();
    updateWrongLettersElement();
    popup.style.display = "none";
});

// Init
displayWord();