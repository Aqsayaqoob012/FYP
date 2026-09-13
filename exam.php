<?php
session_start();

$studentEmail = '';

if (isset($_SESSION['user_email'])) {
    $studentEmail = $_SESSION['user_email'];
} elseif (isset($_SESSION['email'])) {
    $studentEmail = $_SESSION['email'];
}

if (empty($studentEmail)) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Monitor AI - Online Quiz Portal
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .warning-status {
            background-color: #fcf3cf;
            color: #b7950b;
            padding: 12px;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 20px;
            border: 1px solid #f1c40f;
            display: none;
        }

        .question-card {
            background: #fafafa;
            padding: 20px;
            border-left: 5px solid #3498db;
            margin: 20px 0;
            border-radius: 4px;
        }

        .question-card p {
            font-weight: bold;
            color: #34495e;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            height: 80px;
        }

        .submit-btn {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background-color: #27ae60;
        }


        /* ======================================
           CAMERA BOX
           ====================================== */

        .proctor-camera-box {

            position: fixed;

            right: 20px;
            bottom: 20px;

            width: 220px;

            background: white;

            border-radius: 10px;

            overflow: hidden;

            box-shadow:
                0 4px 20px rgba(0,0,0,0.25);

            z-index: 9999;
        }

        .proctor-title {

            background: #2c3e50;

            color: white;

            padding: 8px;

            text-align: center;

            font-size: 13px;

            font-weight: bold;
        }

        #proctorVideo {

            display: block;

            width: 220px;

            height: 160px;

            object-fit: cover;

            background: black;

            transform: scaleX(-1);
        }

        .proctor-status {

            padding: 8px;

            text-align: center;

            background: #ecf0f1;

            font-size: 12px;
        }

        #mainStatus {

            font-weight: bold;

            color: #2c3e50;
        }

        #statusInfo {

            display: block;

            margin-top: 4px;

            color: #7f8c8d;

            font-size: 11px;
        }

        #proctorCanvas {
            display: none;
        }


        @media(max-width: 900px) {

            .proctor-camera-box {

                width: 170px;

                right: 10px;

                bottom: 10px;
            }

            #proctorVideo {

                width: 170px;

                height: 125px;
            }
        }

    </style>

</head>


<body>


<div class="container">

    <h2>
        Monitor AI - Python & Web Development Quiz
    </h2>


    <p>

        ⚠️

        <strong>
            Strict Proctoring Active:
        </strong>

        Camera monitoring, identity verification,
        tab switching, copying and pasting are monitored.

    </p>


    <div
        id="warningBox"
        class="warning-status"
    >

        ⚠️ Warnings:

        <span id="warningCountDisplay">
            0
        </span>

        /3

        <br>

        <span
            id="lastWarningReason"
            style="
                font-size:12px;
                font-weight:normal;
            "
        ></span>

    </div>


    <form id="quizForm">


        <div class="question-card">

            <p>
                Q1: What is the primary role of Flask
                in Python web development?
            </p>


            <label>

                <input
                    type="radio"
                    name="q1"
                    value="A"
                >

                A) Relational Database Management

            </label>

            <br><br>


            <label>

                <input
                    type="radio"
                    name="q1"
                    value="B"
                >

                B) Micro web framework for building
                APIs and web servers

            </label>

            <br><br>


            <label>

                <input
                    type="radio"
                    name="q1"
                    value="C"
                >

                C) CSS Frontend Styling Library

            </label>

        </div>



        <div class="question-card">

            <p>

                Q2: Explain the purpose of a Virtual
                Environment (`venv`) in Python in your own words:

            </p>


            <textarea

                name="q2"

                placeholder="Type your answer here"

                autocomplete="off"

            ></textarea>

        </div>



        <div class="question-card">

            <p>

                Q3: Which library is commonly used
                for face landmark detection in Python?

            </p>


            <input

                type="text"

                name="q3"

                placeholder="Type your answer here..."

                autocomplete="off"

            >

        </div>


        <button
            type="submit"
            class="submit-btn"
        >

            Submit Exam

        </button>


    </form>

</div>



<!-- ==========================================
     MONITOR AI CAMERA
     ========================================== -->

<div
    id="proctorCameraBox"
    class="proctor-camera-box"
>

    <div class="proctor-title">

        🎥 Monitor AI Proctor

    </div>


    <video

        id="proctorVideo"

        autoplay

        muted

        playsinline

    ></video>


    <canvas

        id="proctorCanvas"

        width="320"

        height="240"

    ></canvas>


    <div class="proctor-status">

        <span id="mainStatus">

            Starting Camera...

        </span>


        <span id="statusInfo">

            Initializing proctoring...

        </span>

    </div>

</div>



<script>


// =========================================================
// STUDENT
// =========================================================

const studentEmail =
    <?php echo json_encode($studentEmail); ?>;


// =========================================================
// GENERAL
// =========================================================

let isTerminated = false;

let examSubmitted = false;


let warningCount = 0;

const maxWarnings = 3;


// =========================================================
// CAMERA
// =========================================================

let cameraStream = null;


const video =
    document.getElementById(
        "proctorVideo"
    );


const canvas =
    document.getElementById(
        "proctorCanvas"
    );


const ctx =
    canvas.getContext(
        "2d"
    );


// =========================================================
// MONITOR SETTINGS
// =========================================================


/*
    Lightweight face presence:

    Every 2 seconds.
*/

const PRESENCE_INTERVAL = 2000;


/*
    Full FaceNet identity verification:

    Every 30 seconds.
*/

const NORMAL_IDENTITY_INTERVAL = 30000;


/*
    First mismatch retry:

    5 seconds.
*/

const FIRST_IDENTITY_RETRY = 5000;


/*
    After confirmed wrong person:

    retry every 8 seconds.
*/

const FAILED_IDENTITY_RETRY = 8000;


// =========================================================
// PRESENCE VARIABLES
// =========================================================

let presenceTimer = null;

let presenceCheckRunning = false;


let noFaceChecks = 0;

let multipleFaceChecks = 0;


let previousFaceMissing = false;


let lastPresenceStatus =
    "unknown";


/*
    Prevent duplicate LOOKING_AWAY warnings/events
    while the same continuous incident is active.
*/
let headPoseViolationActive = false;


// =========================================================
// IDENTITY VARIABLES
// =========================================================

let identityTimer = null;

let identityCheckRunning = false;

let identityFailureStreak = 0;


// =========================================================
// STATUS
// =========================================================

function setProctorStatus(
    mainText,
    infoText = ""
) {

    document
        .getElementById(
            "mainStatus"
        )
        .textContent =
            mainText;


    document
        .getElementById(
            "statusInfo"
        )
        .textContent =
            infoText;
}


// =========================================================
// CAPTURE FRAME
// =========================================================

function captureFrame() {

    if (
        !video
        ||
        video.readyState < 2
    ) {

        return null;
    }


    canvas.width =
        320;


    canvas.height =
        240;


    ctx.drawImage(

        video,

        0,

        0,

        canvas.width,

        canvas.height
    );


    return canvas.toDataURL(

        "image/jpeg",

        0.90
    );
}


// =========================================================
// SAVE PROCTOR EVENT
// =========================================================

async function saveProctorEvent(
    eventType,
    direction = null,
    eventDetails = "",
    image = null
) {

    try {

        const evidenceImage =
            image || captureFrame();


        const response =
            await fetch(

                "save_proctor_event.php",

                {

                    method:
                        "POST",

                    headers: {

                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify({

                            event_type:
                                eventType,

                            direction:
                                direction,

                            event_details:
                                eventDetails,

                            image:
                                evidenceImage
                        })
                }
            );


        const data =
            await response.json();


        if (
            data.status ===
            "success"
        ) {

            console.log(
                "PROCTOR EVENT SAVED ✅",
                data
            );
        }
        else {

            console.error(
                "PROCTOR EVENT SAVE FAILED ❌",
                data
            );
        }

    }

    catch (error) {

        /*
            Event logging failure should NOT stop
            or terminate the student's exam.
        */

        console.error(
            "Proctor Event API Error:",
            error
        );
    }
}


// =========================================================
// WARNING
// =========================================================

function handleViolation(
    reason
) {

    if (
        isTerminated
        ||
        examSubmitted
    ) {

        return;
    }


    warningCount++;


    const warningBox =
        document.getElementById(
            "warningBox"
        );


    const warningDisplay =
        document.getElementById(
            "warningCountDisplay"
        );


    const reasonDisplay =
        document.getElementById(
            "lastWarningReason"
        );


    warningBox.style.display =
        "block";


    warningDisplay.textContent =
        warningCount;


    reasonDisplay.textContent =
        "Last violation: " + reason;


    if (
        warningCount >=
        maxWarnings
    ) {

        terminateExam(

            reason
            +
            " Maximum warning limit reached."
        );

        return;
    }


    const remaining =
        maxWarnings -
        warningCount;


    alert(

        `⚠️ WARNING ${warningCount}/${maxWarnings}\n\n`
        +
        `Reason: ${reason}\n\n`
        +
        `${remaining} warning(s) remaining.`
    );
}


// =========================================================
// TERMINATE
// =========================================================

function terminateExam(
    finalReason
) {

    if (isTerminated) {
        return;
    }


    isTerminated =
        true;


    if (presenceTimer) {

        clearInterval(
            presenceTimer
        );
    }


    if (identityTimer) {

        clearTimeout(
            identityTimer
        );
    }


    if (cameraStream) {

        cameraStream
            .getTracks()
            .forEach(

                track =>
                    track.stop()
            );
    }


    document.body.innerHTML = `

        <div
            style="
                min-height:100vh;
                display:flex;
                justify-content:center;
                align-items:center;
                background:#fce4e4;
                font-family:Arial,sans-serif;
                padding:20px;
            "
        >

            <div
                style="
                    max-width:520px;
                    background:white;
                    padding:40px;
                    border-radius:12px;
                    text-align:center;
                    box-shadow:0 4px 20px rgba(0,0,0,.15);
                    border-top:6px solid #e74c3c;
                "
            >

                <h1
                    style="
                        color:#c0392b;
                    "
                >

                    ❌ EXAM TERMINATED

                </h1>


                <p>

                    Monitor AI detected repeated
                    proctoring violations.

                </p>


                <div
                    style="
                        background:#f9d5d5;
                        padding:12px;
                        border-radius:6px;
                        color:#900c3f;
                        font-weight:bold;
                    "
                >

                    ${finalReason}

                </div>


                <p
                    style="
                        font-size:13px;
                        color:#777;
                        margin-top:20px;
                    "
                >

                    You cannot continue this exam.

                </p>

            </div>

        </div>

    `;
}


// =========================================================
// START CAMERA
// =========================================================

async function startProctorCamera() {

    try {

        cameraStream =
            await navigator
                .mediaDevices
                .getUserMedia({

                    video: {

                        width: {
                            ideal: 640
                        },

                        height: {
                            ideal: 480
                        },

                        facingMode:
                            "user"
                    },

                    audio:
                        false
                });


        video.srcObject =
            cameraStream;


        await video.play();


        setProctorStatus(

            "🟢 Camera Active",

            "Monitor AI is watching the exam"
        );


        const videoTrack =
            cameraStream
                .getVideoTracks()[0];


        videoTrack.addEventListener(

            "ended",

            function () {

                if (
                    !isTerminated
                    &&
                    !examSubmitted
                ) {

                    saveProctorEvent(

                        "CAMERA_DISABLED",

                        null,

                        "The proctoring camera stream was disabled during the exam.",

                        captureFrame()
                    );


                    terminateExam(

                        "Proctoring camera was disabled."
                    );
                }
            }
        );


        /*
            Presence every 2 sec.
        */

        startPresenceMonitoring();


        /*
            First identity check shortly
            after exam starts.
        */

        scheduleIdentityCheck(
            5000
        );

    }

    catch (error) {

        console.error(
            "Camera Error:",
            error
        );


        terminateExam(

            "Camera access is required for the exam."
        );
    }
}


// =========================================================
// START PRESENCE MONITOR
// =========================================================

function startPresenceMonitoring() {

    if (presenceTimer) {

        clearInterval(
            presenceTimer
        );
    }


    presenceTimer =
        setInterval(

            checkFacePresence,

            PRESENCE_INTERVAL
        );
}


// =========================================================
// PRESENCE CHECK
// =========================================================

async function checkFacePresence() {

    if (
        isTerminated
        ||
        examSubmitted
        ||
        presenceCheckRunning
    ) {

        return;
    }


    presenceCheckRunning =
        true;


    const image =
        captureFrame();


    if (!image) {

        presenceCheckRunning =
            false;

        return;
    }


    try {

        const response =
            await fetch(

                "http://127.0.0.1:5000/check_exam_presence",

                {

                    method:
                        "POST",

                    headers: {

                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify({

                            image:
                                image
                        })
                }
            );


        const data =
            await response.json();


        // =================================================
        // ONE FACE PRESENT
        // =================================================

        if (
            data.status ===
            "present"
        ) {

            const returnedAfterMissing =
                previousFaceMissing;


            lastPresenceStatus =
                "present";


            noFaceChecks =
                0;


            multipleFaceChecks =
                0;


            previousFaceMissing =
                false;


            // =================================================
            // HEAD POSE MONITORING
            // =================================================

            if (
                data.alert ===
                "looking_away"
            ) {

                if (
                    !identityCheckRunning
                ) {

                    setProctorStatus(

                        "⚠️ Looking Away",

                        `Please look at the screen (${data.direction})`
                    );
                }


                /*
                    Only one warning + one DB event
                    for one continuous looking-away incident.
                */

                if (
                    !headPoseViolationActive
                ) {

                    headPoseViolationActive =
                        true;


                    saveProctorEvent(

                        "LOOKING_AWAY",

                        data.direction || null,

                        "Student looked away from the screen for more than 5 seconds.",

                        image
                    );


                    handleViolation(

                        "Student was looking away from the screen for more than 5 seconds."
                    );
                }
            }

            else if (
                data.looking_away
            ) {

                if (
                    !identityCheckRunning
                ) {

                    setProctorStatus(

                        "🟡 Looking Away",

                        `Please look at the screen (${data.direction})`
                    );
                }
            }

            else {

                /*
                    Student is facing forward again.
                    Allow a future looking-away incident
                    to create a new warning/event.
                */

                headPoseViolationActive =
                    false;


                if (
                    !identityCheckRunning
                ) {

                    setProctorStatus(

                        "🟢 Student Visible",

                        "Identity monitoring active"
                    );
                }
            }


            /*
                Person disappeared and came back.

                Immediately verify identity
                because another person may have
                taken their place.
            */

            if (
                returnedAfterMissing
                &&
                !identityCheckRunning
            ) {

                scheduleIdentityCheck(
                    500
                );
            }


            presenceCheckRunning =
                false;

            return;
        }


        // =================================================
        // NO FACE
        // =================================================

        if (
            data.status ===
            "no_face"
        ) {

            lastPresenceStatus =
                "no_face";


            previousFaceMissing =
                true;


            noFaceChecks++;


            multipleFaceChecks =
                0;


            setProctorStatus(

                "⚠️ Face Not Visible",

                "Please return to camera view"
            );


            /*
                Check every 2 seconds:

                3 checks  = ~6 sec  -> warning
                8 checks  = ~16 sec -> warning
                13 checks = ~26 sec -> warning
            */

            if (
                noFaceChecks === 3
                ||
                noFaceChecks === 8
                ||
                noFaceChecks === 13
            ) {

                saveProctorEvent(

                    "FACE_MISSING",

                    null,

                    `Student was not visible in the camera. Presence check count: ${noFaceChecks}.`,

                    image
                );


                handleViolation(

                    "Student is not visible in the camera."
                );
            }


            presenceCheckRunning =
                false;

            return;
        }


        // =================================================
        // MULTIPLE FACES
        // =================================================

        if (
            data.status ===
            "multiple_faces"
        ) {

            lastPresenceStatus =
                "multiple_faces";


            noFaceChecks =
                0;


            multipleFaceChecks++;


            setProctorStatus(

                "⚠️ Multiple Faces Detected",

                "Only the registered student should be visible"
            );


            /*
                Immediate warning first time.

                If multiple people continue:
                another warning after ~10 sec,
                another after another ~10 sec.
            */

            if (
                multipleFaceChecks === 1
                ||
                multipleFaceChecks === 6
                ||
                multipleFaceChecks === 11
            ) {

                saveProctorEvent(

                    "MULTIPLE_FACES",

                    null,

                    `Multiple faces detected. Presence check count: ${multipleFaceChecks}.`,

                    image
                );


                handleViolation(

                    "More than one person detected in the camera."
                );
            }


            presenceCheckRunning =
                false;

            return;
        }


        presenceCheckRunning =
            false;

    }

    catch (error) {

        console.error(
            "Presence API Error:",
            error
        );


        presenceCheckRunning =
            false;
    }
}


// =========================================================
// SCHEDULE IDENTITY CHECK
// =========================================================

function scheduleIdentityCheck(
    delay =
        NORMAL_IDENTITY_INTERVAL
) {

    if (
        isTerminated
        ||
        examSubmitted
    ) {

        return;
    }


    if (identityTimer) {

        clearTimeout(
            identityTimer
        );
    }


    identityTimer =
        setTimeout(

            verifyStudentIdentity,

            delay
        );
}


// =========================================================
// FULL FACENET IDENTITY CHECK
// =========================================================

async function verifyStudentIdentity() {

    if (
        isTerminated
        ||
        examSubmitted
        ||
        identityCheckRunning
    ) {

        return;
    }


    /*
        Don't perform heavy FaceNet verification
        while presence monitor says no face or
        multiple faces.
    */

    if (
        lastPresenceStatus !==
        "present"
    ) {

        scheduleIdentityCheck(
            5000
        );

        return;
    }


    identityCheckRunning =
        true;


    setProctorStatus(

        "🔍 Verifying Identity...",

        "Checking registered student"
    );


    const image =
        captureFrame();


    if (!image) {

        identityCheckRunning =
            false;


        scheduleIdentityCheck(
            5000
        );

        return;
    }


    try {

        const response =
            await fetch(

                "http://127.0.0.1:5000/monitor_exam",

                {

                    method:
                        "POST",

                    headers: {

                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify({

                            email:
                                studentEmail,

                            image:
                                image
                        })
                }
            );


        const data =
            await response.json();


        console.log(
            "Identity Response:",
            data
        );


        // =================================================
        // SAME PERSON
        // =================================================

        if (
            data.status ===
            "success"
        ) {

            identityFailureStreak =
                0;


            setProctorStatus(

                "✅ Identity Verified",

                "Registered student confirmed"
            );


            identityCheckRunning =
                false;


            scheduleIdentityCheck(
                NORMAL_IDENTITY_INTERVAL
            );

            return;
        }


        // =================================================
        // DIFFERENT PERSON
        // =================================================

        if (
            data.status ===
            "failed"
        ) {

            identityFailureStreak++;


            identityCheckRunning =
                false;


            /*
                First mismatch:
                don't immediately warn.

                Could be lighting / movement.
            */

            if (
                identityFailureStreak === 1
            ) {

                setProctorStatus(

                    "🟡 Rechecking Identity",

                    "Please face the camera"
                );


                scheduleIdentityCheck(
                    FIRST_IDENTITY_RETRY
                );

                return;
            }


            /*
                Second consecutive mismatch confirms
                likely different person.
            */

            saveProctorEvent(

                "WRONG_PERSON",

                null,

                `Identity verification failed ${identityFailureStreak} consecutive times.`,

                image
            );


            handleViolation(

                "A different person appears to be taking the exam."
            );


            if (!isTerminated) {

                scheduleIdentityCheck(
                    FAILED_IDENTITY_RETRY
                );
            }


            return;
        }


        // =================================================
        // NO FACE
        // =================================================

        if (
            data.status ===
            "no_face"
        ) {

            identityCheckRunning =
                false;


            scheduleIdentityCheck(
                5000
            );

            return;
        }


        // =================================================
        // MULTIPLE FACES
        // =================================================

        if (
            data.status ===
            "multiple_faces"
        ) {

            identityCheckRunning =
                false;


            scheduleIdentityCheck(
                5000
            );

            return;
        }


        // =================================================
        // BACKEND ERROR
        // =================================================

        if (
            data.status ===
            "error"
        ) {

            console.error(
                data.message
            );


            setProctorStatus(

                "⚠️ Verification Service Error",

                "Automatic retry in progress"
            );


            identityCheckRunning =
                false;


            scheduleIdentityCheck(
                10000
            );

            return;
        }


        identityCheckRunning =
            false;


        scheduleIdentityCheck(
            10000
        );

    }

    catch (error) {

        console.error(
            "Identity Check Error:",
            error
        );


        setProctorStatus(

            "⚠️ Backend Connection Error",

            "Trying again automatically"
        );


        identityCheckRunning =
            false;


        scheduleIdentityCheck(
            10000
        );
    }
}


// =========================================================
// TAB SWITCH
// =========================================================

document.addEventListener(

    "visibilitychange",

    function () {

        if (
            document.hidden
            &&
            !examSubmitted
        ) {

            saveProctorEvent(

                "TAB_SWITCH",

                null,

                "User switched tab or minimized the browser window.",

                captureFrame()
            );


            handleViolation(

                "User switched tab or minimized the browser."
            );
        }
    }
);


// =========================================================
// COPY
// =========================================================

document.addEventListener(

    "copy",

    function (e) {

        if (examSubmitted) {
            return;
        }


        e.preventDefault();


        saveProctorEvent(

            "COPY_ATTEMPT",

            null,

            "User attempted to copy quiz content.",

            captureFrame()
        );


        handleViolation(

            "Attempted to copy quiz content."
        );
    }
);


// =========================================================
// PASTE
// =========================================================

document.addEventListener(

    "paste",

    function (e) {

        if (examSubmitted) {
            return;
        }


        e.preventDefault();


        saveProctorEvent(

            "PASTE_ATTEMPT",

            null,

            "User attempted to paste content into the exam.",

            captureFrame()
        );


        handleViolation(

            "Attempted to paste into the exam."
        );
    }
);


// =========================================================
// RIGHT CLICK
// =========================================================

document.addEventListener(

    "contextmenu",

    function (e) {

        if (examSubmitted) {
            return;
        }


        e.preventDefault();


        saveProctorEvent(

            "RIGHT_CLICK",

            null,

            "User attempted to open the right-click context menu.",

            captureFrame()
        );


        handleViolation(

            "Attempted to use the right-click menu."
        );
    }
);


// =========================================================
// SUBMIT
// =========================================================

document
    .getElementById(
        "quizForm"
    )
    .addEventListener(

        "submit",

        function (e) {

            e.preventDefault();


            if (isTerminated) {
                return;
            }


            examSubmitted =
                true;


            if (presenceTimer) {

                clearInterval(
                    presenceTimer
                );
            }


            if (identityTimer) {

                clearTimeout(
                    identityTimer
                );
            }


            if (cameraStream) {

                cameraStream
                    .getTracks()
                    .forEach(

                        track =>
                            track.stop()
                    );
            }


            saveProctorEvent(

                "EXAM_SUBMITTED",

                null,

                "Student submitted the exam.",

                null
            );


            alert(
                "✅ Exam submitted successfully!"
            );
        }
    );


// =========================================================
// START
// =========================================================

window.addEventListener(

    "load",

    startProctorCamera
);


</script>


</body>

</html>