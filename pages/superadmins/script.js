// class SessionTimer {
//     constructor(timerId, duration, logoutUrl) {
//         this.timerElement = document.getElementById(timerId);
//         this.initialDuration = duration;
//         this.duration = duration;
//         this.logoutUrl = logoutUrl;
//         this.interval = null;
//         this.startTimer();
//         this.addEventListeners();
//     }

//     startTimer() {
//         this.interval = setInterval(() => this.updateTimer(), 1000);
//     }

//     updateTimer() {
//         this.duration--;
//         if (this.duration < 1) {
//             window.location = this.logoutUrl;
//         } else {
//             this.timerElement.innerText = this.duration;
//         }
//     }

//     resetTimer() {
//         this.duration = this.initialDuration;
//     }

//     addEventListeners() {
//         window.addEventListener("mousemove", () => this.resetTimer());
//     }
// }

// const sessionTimer = new SessionTimer("timer", 200, "logout.php");
