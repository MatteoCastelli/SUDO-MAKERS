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
    "I pilastri della terra",
    "L albero delle bugie",
    "Una ragazza senza ricordi",
    "L Ultimo giorno di un condannato",
    "La biblioteca perduta",
    "L uomo che scambio sua moglie per un cappello",
    "Una vita come tante",
    "L ultimo segreto",
    "Sotto mentite spoglie",
    "Normal People",
    "The Let Them Theory",
    "Humankind",
    "Careless People",
    "Mandorla amara",
    "La levatrice",
    "I burger di Ciccio",
    "Un giorno questo dolore ti sara utile",
    "Se i gatti scomparissero dal mondo",
    "La bussola d'oro"
];
let selectedWord = words[Math.floor(Math.random() * words.length)];

let playable = true;

const correctLetters = [];
const wrongLetters = [];

// test
function displayWord() {
    wordElement.innerHTML = `
        ${selectedWord
        .split("")
        .map(
            (letter) => `
                    <span class="letter">
                        ${letter === " " ? " " : correctLetters.includes(letter) ? letter : ""}
                    </span>
                `
        )
        .join("")}
    `;

    // rimuove gli spazi per il confronto
    const innerWord = wordElement.innerText.replace(/\n/g, "").replace(/ /g, "");
    const targetWord = selectedWord.replace(/ /g, "");

    if (innerWord === targetWord) {
        finalMessage.innerText = "Congratulazioni! Hai vinto!";
        finalMessageRevealWord.innerText = "";
        popup.style.display = "flex";
        playable = false;
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