import os

os.environ["PROTOCOL_BUFFERS_PYTHON_IMPLEMENTATION"] = "python"
os.environ["TF_USE_LEGACY_KERAS"] = "1"
os.environ["TF_CPP_MIN_LOG_LEVEL"] = "2"

import base64
import json
import traceback
import uuid

import cv2
import numpy as np

from flask import (
    Flask,
    jsonify,
    request
)

from flask_cors import CORS

from deepface import DeepFace


# =========================================================
# FLASK
# =========================================================

app = Flask(__name__)

CORS(
    app,
    resources={
        r"/*": {
            "origins": "*"
        }
    }
)

os.makedirs(
    "saved_faces",
    exist_ok=True
)

os.makedirs(
    "login_temp",
    exist_ok=True
)

os.makedirs(
    "exam_temp",
    exist_ok=True
)


# =========================================================
# MEDIAPIPE
# =========================================================

try:

    import mediapipe.python.solutions.face_mesh as mp_face_mesh


    # Registration + login pose detection
    face_mesh = mp_face_mesh.FaceMesh(

        max_num_faces=1,

        refine_landmarks=True,

        min_detection_confidence=0.5,

        min_tracking_confidence=0.5
    )


    # Exam presence monitoring
    # Allows detection of 2 people.
    exam_face_mesh = mp_face_mesh.FaceMesh(

        max_num_faces=2,

        refine_landmarks=False,

        min_detection_confidence=0.5,

        min_tracking_confidence=0.5
    )


    print(
        "Mediapipe FaceMesh loaded successfully ✅"
    )


except Exception as e:

    print(
        "Mediapipe Error:",
        str(e)
    )

    face_mesh = None

    exam_face_mesh = None


# =========================================================
# SETTINGS
# =========================================================

YAW_THRESHOLD = 0.09

PITCH_THRESHOLD = 8.0


FORWARD_YAW = 0.07

FORWARD_PITCH = 7.0


FACE_MATCH_THRESHOLD = 0.40


# =========================================================
# EMBEDDING CACHE
# =========================================================

registered_embedding_cache = {}


# =========================================================
# PROFILE
# =========================================================

def get_profile_path(email):

    return os.path.join(

        "saved_faces",

        f"{email}_pose_profile.json"
    )


def load_pose_profile(email):

    path = get_profile_path(
        email
    )


    if not os.path.exists(path):

        return {

            "left_sign":
                None,

            "up_sign":
                None
        }


    try:

        with open(

            path,

            "r",

            encoding="utf-8"

        ) as f:

            data = json.load(f)


        return {

            "left_sign":
                data.get(
                    "left_sign"
                ),

            "up_sign":
                data.get(
                    "up_sign"
                )
        }


    except Exception as e:

        print(

            "Profile Load Error:",

            str(e)
        )


        return {

            "left_sign":
                None,

            "up_sign":
                None
        }


def save_pose_profile(
    email,
    profile
):

    path = get_profile_path(
        email
    )


    try:

        with open(

            path,

            "w",

            encoding="utf-8"

        ) as f:

            json.dump(

                profile,

                f,

                indent=4
            )


        return True


    except Exception as e:

        print(

            "Profile Save Error:",

            str(e)
        )


        return False


# =========================================================
# IMAGE DECODER
# =========================================================

def decode_image(
    image_data
):

    try:

        if not image_data:

            return None


        if "," in image_data:

            image_data = (

                image_data
                .split(
                    ",",
                    1
                )[1]
            )


        decoded = base64.b64decode(
            image_data
        )


        array = np.frombuffer(

            decoded,

            np.uint8
        )


        image = cv2.imdecode(

            array,

            cv2.IMREAD_COLOR
        )


        return image


    except Exception as e:

        print(

            "Image Decode Error:",

            str(e)
        )


        return None


# =========================================================
# EXAM FACE COUNT
# =========================================================

def count_exam_faces(
    image
):

    if (
        exam_face_mesh is None
        or
        image is None
    ):

        return 0


    try:

        rgb_image = cv2.cvtColor(

            image,

            cv2.COLOR_BGR2RGB
        )


        results = exam_face_mesh.process(
            rgb_image
        )


        if not results.multi_face_landmarks:

            return 0


        return len(
            results.multi_face_landmarks
        )


    except Exception as e:

        print(

            "Face Count Error:",

            str(e)
        )


        return 0


# =========================================================
# FACE METRICS
# =========================================================

def get_face_metrics(
    image
):

    if (
        face_mesh is None
        or
        image is None
    ):

        return None


    try:

        img_h, img_w, _ = (
            image.shape
        )


        rgb_image = cv2.cvtColor(

            image,

            cv2.COLOR_BGR2RGB
        )


        results = face_mesh.process(
            rgb_image
        )


        if not results.multi_face_landmarks:

            return None


        landmarks = (

            results
            .multi_face_landmarks[0]
            .landmark
        )


        # =================================================
        # LEFT / RIGHT
        # =================================================

        left_eye = landmarks[33]

        right_eye = landmarks[263]

        nose = landmarks[1]


        left_eye_x = (
            left_eye.x
            *
            img_w
        )


        right_eye_x = (
            right_eye.x
            *
            img_w
        )


        nose_x = (
            nose.x
            *
            img_w
        )


        eye_mid_x = (

            left_eye_x
            +
            right_eye_x

        ) / 2.0


        eye_distance = abs(

            right_eye_x
            -
            left_eye_x
        )


        if eye_distance < 1:

            return None


        yaw_score = (

            nose_x
            -
            eye_mid_x

        ) / eye_distance


        # =================================================
        # UP / DOWN
        # =================================================

        landmark_ids = [

            1,

            152,

            33,

            263,

            61,

            291
        ]


        image_points = []


        for idx in landmark_ids:

            lm = landmarks[idx]


            image_points.append([

                lm.x * img_w,

                lm.y * img_h
            ])


        image_points = np.array(

            image_points,

            dtype=np.float64
        )


        model_points = np.array(

            [

                (
                    0.0,
                    0.0,
                    0.0
                ),

                (
                    0.0,
                    -330.0,
                    -65.0
                ),

                (
                    -225.0,
                    170.0,
                    -135.0
                ),

                (
                    225.0,
                    170.0,
                    -135.0
                ),

                (
                    -150.0,
                    -150.0,
                    -125.0
                ),

                (
                    150.0,
                    -150.0,
                    -125.0
                )

            ],

            dtype=np.float64
        )


        focal_length = img_w


        center = (

            img_w / 2,

            img_h / 2
        )


        camera_matrix = np.array(

            [

                [

                    focal_length,

                    0,

                    center[0]
                ],

                [

                    0,

                    focal_length,

                    center[1]
                ],

                [

                    0,

                    0,

                    1
                ]

            ],

            dtype=np.float64
        )


        dist_coeffs = np.zeros(

            (4, 1),

            dtype=np.float64
        )


        (
            success,
            rotation_vector,
            translation_vector

        ) = cv2.solvePnP(

            model_points,

            image_points,

            camera_matrix,

            dist_coeffs,

            flags=
                cv2.SOLVEPNP_ITERATIVE
        )


        if not success:

            return None


        rotation_matrix, _ = (

            cv2.Rodrigues(
                rotation_vector
            )
        )


        (
            angles,
            _,
            _,
            _,
            _,
            _

        ) = cv2.RQDecomp3x3(

            rotation_matrix
        )


        pitch = float(
            angles[0]
        )


        if pitch > 90:

            pitch = (
                180
                -
                pitch
            )


        elif pitch < -90:

            pitch = (
                -180
                -
                pitch
            )


        print(

            "POSE SCORE -> "

            f"YawScore: "
            f"{yaw_score:.3f}"

            " | "

            f"Pitch: "
            f"{pitch:.2f}"
        )


        return {

            "yaw":
                yaw_score,

            "pitch":
                pitch
        }


    except Exception as e:

        print(

            "Face Metrics Error:",

            str(e)
        )


        return None


# =========================================================
# CLASSIFY LOGIN POSE
# =========================================================

def classify_pose(
    metrics,
    email
):

    if metrics is None:

        return "No Face"


    yaw = metrics[
        "yaw"
    ]


    pitch = metrics[
        "pitch"
    ]


    profile = load_pose_profile(
        email
    )


    left_sign = profile.get(
        "left_sign"
    )


    up_sign = profile.get(
        "up_sign"
    )


    if abs(yaw) >= YAW_THRESHOLD:

        current_sign = (

            1
            if
            yaw > 0
            else
            -1
        )


        if left_sign is not None:

            if (
                current_sign
                ==
                left_sign
            ):

                print(
                    "DETECTED -> Left"
                )


                return "Left"


            else:

                print(
                    "DETECTED -> Right"
                )


                return "Right"


    if (
        abs(pitch)
        >=
        PITCH_THRESHOLD
    ):

        current_sign = (

            1
            if
            pitch > 0
            else
            -1
        )


        if up_sign is not None:

            if (
                current_sign
                ==
                up_sign
            ):

                print(
                    "DETECTED -> Up"
                )


                return "Up"


            else:

                print(
                    "DETECTED -> Down"
                )


                return "Down"


    if (

        abs(yaw)
        <=
        FORWARD_YAW

        and

        abs(pitch)
        <=
        FORWARD_PITCH
    ):

        print(
            "DETECTED -> Forward"
        )


        return "Forward"


    print(
        "DETECTED -> Forward"
    )


    return "Forward"


# =========================================================
# REGISTRATION POSE CHECK
# =========================================================

def check_registration_pose(
    target,
    metrics,
    email
):

    if metrics is None:

        return (
            False,
            "No Face"
        )


    yaw = metrics[
        "yaw"
    ]


    pitch = metrics[
        "pitch"
    ]


    profile = load_pose_profile(
        email
    )


    # =====================================================
    # FORWARD
    # =====================================================

    if target == "Forward":

        if (

            abs(yaw)
            <=
            FORWARD_YAW

            and

            abs(pitch)
            <=
            FORWARD_PITCH
        ):

            print(
                "FORWARD DETECTED ✅"
            )


            return (
                True,
                "Forward"
            )


        return (
            False,
            "Move Face Forward"
        )


    # =====================================================
    # LEFT
    # =====================================================

    if target == "Left":

        if (
            abs(yaw)
            >=
            YAW_THRESHOLD
        ):

            sign = (

                1
                if
                yaw > 0
                else
                -1
            )


            profile[
                "left_sign"
            ] = sign


            save_pose_profile(

                email,

                profile
            )


            print(

                "LEFT CALIBRATED ✅ "

                f"Sign = {sign}"
            )


            return (
                True,
                "Left"
            )


        return (
            False,
            "Forward"
        )


    # =====================================================
    # RIGHT
    # =====================================================

    if target == "Right":

        left_sign = profile.get(
            "left_sign"
        )


        if left_sign is None:

            return (
                False,
                "Left not calibrated"
            )


        if (
            abs(yaw)
            >=
            YAW_THRESHOLD
        ):

            current_sign = (

                1
                if
                yaw > 0
                else
                -1
            )


            if (
                current_sign
                ==
                -left_sign
            ):

                print(
                    "RIGHT DETECTED ✅"
                )


                return (
                    True,
                    "Right"
                )


            return (
                False,
                "Left"
            )


        return (
            False,
            "Forward"
        )


    # =====================================================
    # UP
    # =====================================================

    if target == "Up":

        if (

            abs(pitch)
            >=
            PITCH_THRESHOLD

            and

            abs(yaw)
            <
            0.22
        ):

            sign = (

                1
                if
                pitch > 0
                else
                -1
            )


            profile[
                "up_sign"
            ] = sign


            save_pose_profile(

                email,

                profile
            )


            print(

                "UP CALIBRATED ✅ "

                f"Sign = {sign}"
            )


            return (
                True,
                "Up"
            )


        return (
            False,
            "Forward"
        )


    # =====================================================
    # DOWN
    # =====================================================

    if target == "Down":

        up_sign = profile.get(
            "up_sign"
        )


        if up_sign is None:

            return (
                False,
                "Up not calibrated"
            )


        if (

            abs(pitch)
            >=
            PITCH_THRESHOLD

            and

            abs(yaw)
            <
            0.22
        ):

            current_sign = (

                1
                if
                pitch > 0
                else
                -1
            )


            if (
                current_sign
                ==
                -up_sign
            ):

                print(
                    "DOWN DETECTED ✅"
                )


                return (
                    True,
                    "Down"
                )


            return (
                False,
                "Up"
            )


        return (
            False,
            "Forward"
        )


    return (
        False,
        "Unknown"
    )


# =========================================================
# COSINE DISTANCE
# =========================================================

def calculate_cosine_distance(
    embedding1,
    embedding2
):

    embedding1 = np.array(

        embedding1,

        dtype=np.float64
    )


    embedding2 = np.array(

        embedding2,

        dtype=np.float64
    )


    dot_product = np.dot(

        embedding1,

        embedding2
    )


    norm1 = np.linalg.norm(
        embedding1
    )


    norm2 = np.linalg.norm(
        embedding2
    )


    if (
        norm1 == 0
        or
        norm2 == 0
    ):

        return 999.0


    cosine_similarity = (

        dot_product

        /

        (
            norm1
            *
            norm2
        )
    )


    cosine_distance = (

        1
        -
        cosine_similarity
    )


    return float(
        cosine_distance
    )


# =========================================================
# CREATE EMBEDDING
# =========================================================

def create_face_embedding(
    image_path
):

    result = DeepFace.represent(

        img_path=
            image_path,

        model_name=
            "Facenet",

        detector_backend=
            "skip",

        enforce_detection=
            False
    )


    if not result:

        raise Exception(
            "Face embedding is empty"
        )


    return result[0][
        "embedding"
    ]


# =========================================================
# GET REGISTERED EMBEDDING
# =========================================================

def get_registered_embedding(
    image_path
):

    absolute_path = os.path.abspath(
        image_path
    )


    modification_time = os.path.getmtime(
        absolute_path
    )


    cache_key = (

        absolute_path,

        modification_time
    )


    if (
        cache_key
        in
        registered_embedding_cache
    ):

        return (
            registered_embedding_cache[
                cache_key
            ]
        )


    old_keys = [

        key

        for key in
        list(
            registered_embedding_cache.keys()
        )

        if key[0]
        ==
        absolute_path
    ]


    for key in old_keys:

        registered_embedding_cache.pop(
            key,
            None
        )


    embedding = create_face_embedding(
        absolute_path
    )


    registered_embedding_cache[
        cache_key
    ] = embedding


    return embedding


# =========================================================
# REGISTRATION API
# =========================================================

@app.route(
    "/process_frame",
    methods=["POST"]
)
def process_frame():

    try:

        data = request.get_json(
            silent=True
        )


        if not data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "No data received"
            })


        target_dir = data.get(
            "target"
        )


        email = data.get(
            "email"
        )


        image_data = data.get(
            "image"
        )


        if not target_dir:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Target pose missing"
            })


        if not email:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Email missing"
            })


        if not image_data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Image missing"
            })


        image = decode_image(
            image_data
        )


        if image is None:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Invalid image"
            })


        metrics = get_face_metrics(
            image
        )


        if metrics is None:

            print(

                f"REGISTER -> "
                f"Target: {target_dir} | "
                f"Detected: No Face"
            )


            return jsonify({

                "status":
                    "scanning",

                "current_pose":
                    "No Face",

                "target_pose":
                    target_dir
            })


        matched, detected = (
            check_registration_pose(

                target_dir,

                metrics,

                email
            )
        )


        print(

            f"REGISTER -> "
            f"Target: {target_dir} | "
            f"Detected: {detected}"
        )


        if matched:

            filename = os.path.join(

                "saved_faces",

                f"{email}_{target_dir}.jpg"
            )


            saved = cv2.imwrite(

                filename,

                image
            )


            if not saved:

                return jsonify({

                    "status":
                        "error",

                    "message":
                        "Image could not be saved"
                })


            absolute_filename = (
                os.path.abspath(
                    filename
                )
            )


            keys_to_delete = [

                key

                for key in
                list(
                    registered_embedding_cache.keys()
                )

                if key[0]
                ==
                absolute_filename
            ]


            for key in keys_to_delete:

                registered_embedding_cache.pop(

                    key,

                    None
                )


            print("")
            print(
                "================================"
            )
            print(
                f"{target_dir} CAPTURE SUCCESS ✅"
            )
            print(
                f"IMAGE SAVED -> {filename}"
            )
            print(
                "================================"
            )
            print("")


            return jsonify({

                "status":
                    "match",

                "message":
                    f"{target_dir} captured successfully",

                "current_pose":
                    target_dir,

                "yaw_score":
                    round(
                        metrics["yaw"],
                        3
                    ),

                "pitch":
                    round(
                        metrics["pitch"],
                        2
                    )
            })


        return jsonify({

            "status":
                "scanning",

            "current_pose":
                detected,

            "target_pose":
                target_dir,

            "yaw_score":
                round(
                    metrics["yaw"],
                    3
                ),

            "pitch":
                round(
                    metrics["pitch"],
                    2
                )
        })


    except Exception as e:

        print(

            "Process Frame Error:",

            str(e)
        )


        traceback.print_exc()


        return jsonify({

            "status":
                "error",

            "message":
                str(e)
        })


# =========================================================
# LOGIN API
# =========================================================

@app.route(
    "/verify_login",
    methods=["POST"]
)
def verify_login():

    temp_img_path = None


    try:

        data = request.get_json(
            silent=True
        )


        if not data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "No data received"
            })


        target_dir = data.get(
            "target"
        )


        email = data.get(
            "email"
        )


        image_data = data.get(
            "image"
        )


        if not target_dir:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Target missing"
            })


        if not email:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Email missing"
            })


        if not image_data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Image missing"
            })


        profile = load_pose_profile(
            email
        )


        if (

            profile.get(
                "left_sign"
            )
            is None

            or

            profile.get(
                "up_sign"
            )
            is None
        ):

            return jsonify({

                "status":
                    "error",

                "message":
                    "Pose calibration not found. "
                    "Please register again."
            })


        saved_img_path = os.path.abspath(

            os.path.join(

                "saved_faces",

                f"{email}_{target_dir}.jpg"
            )
        )


        if not os.path.exists(
            saved_img_path
        ):

            saved_img_path = os.path.abspath(

                os.path.join(

                    "saved_faces",

                    f"{email}_Forward.jpg"
                )
            )


        if not os.path.exists(
            saved_img_path
        ):

            return jsonify({

                "status":
                    "error",

                "message":
                    "Registered face image not found."
            })


        image = decode_image(
            image_data
        )


        if image is None:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Invalid camera image"
            })


        metrics = get_face_metrics(
            image
        )


        if metrics is None:

            return jsonify({

                "status":
                    "scanning",

                "current_pose":
                    "No Face",

                "target_pose":
                    target_dir
            })


        current_pose = classify_pose(

            metrics,

            email
        )


        print(

            f"LOGIN -> "
            f"Target: {target_dir} | "
            f"Detected: {current_pose}"
        )


        if (
            current_pose
            !=
            target_dir
        ):

            return jsonify({

                "status":
                    "scanning",

                "current_pose":
                    current_pose,

                "target_pose":
                    target_dir
            })


        temp_img_path = os.path.abspath(

            os.path.join(

                "login_temp",

                f"{uuid.uuid4().hex}.jpg"
            )
        )


        saved = cv2.imwrite(

            temp_img_path,

            image
        )


        if not saved:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Current login image could not be saved."
            })


        print("")
        print(
            "========================================"
        )
        print(
            "LOGIN FACE VERIFICATION"
        )
        print(
            "Current Image ->",
            temp_img_path
        )
        print(
            "Registered Image ->",
            saved_img_path
        )
        print(
            "========================================"
        )


        print(
            "Creating CURRENT face embedding..."
        )


        current_embedding = (
            create_face_embedding(
                temp_img_path
            )
        )


        print(
            "Current embedding created ✅"
        )


        registered_embedding = (
            get_registered_embedding(
                saved_img_path
            )
        )


        print(
            "Registered embedding ready ✅"
        )


        cosine_distance = (
            calculate_cosine_distance(

                current_embedding,

                registered_embedding
            )
        )


        print("")
        print(

            f"COSINE DISTANCE -> "
            f"{cosine_distance:.4f}"
        )

        print(

            f"THRESHOLD -> "
            f"{FACE_MATCH_THRESHOLD}"
        )


        if (
            cosine_distance
            <=
            FACE_MATCH_THRESHOLD
        ):

            print(
                "FACE MATCHED ✅"
            )

            print(
                f"LOGIN {target_dir} SUCCESS ✅"
            )


            return jsonify({

                "status":
                    "success",

                "message":
                    "Face Matched!",

                "current_pose":
                    current_pose,

                "distance":
                    round(
                        cosine_distance,
                        4
                    ),

                "threshold":
                    FACE_MATCH_THRESHOLD
            })


        print(
            "FACE DOES NOT MATCH ❌"
        )


        return jsonify({

            "status":
                "failed",

            "message":
                "Face does not match! Access Denied.",

            "distance":
                round(
                    cosine_distance,
                    4
                ),

            "threshold":
                FACE_MATCH_THRESHOLD
        })


    except Exception as e:

        print("")
        print(
            "VERIFY LOGIN FULL ERROR ❌"
        )

        traceback.print_exc()

        print("")


        return jsonify({

            "status":
                "error",

            "message":
                str(e)
        })


    finally:

        if (
            temp_img_path
            and
            os.path.exists(
                temp_img_path
            )
        ):

            try:

                os.remove(
                    temp_img_path
                )


            except Exception as e:

                print(

                    "Login Temp Delete Error:",

                    str(e)
                )


# =========================================================
# EXAM PRESENCE CHECK
# =========================================================

@app.route(
    "/check_exam_presence",
    methods=["POST"]
)
def check_exam_presence():

    try:

        data = request.get_json(
            silent=True
        )


        if not data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "No data received"
            })


        image_data = data.get(
            "image"
        )


        if not image_data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Camera image missing"
            })


        image = decode_image(
            image_data
        )


        if image is None:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Invalid camera image"
            })


        face_count = count_exam_faces(
            image
        )


        print(

            "EXAM PRESENCE -> "

            f"Faces detected: "
            f"{face_count}"
        )


        if face_count == 0:

            return jsonify({

                "status":
                    "no_face",

                "face_count":
                    0,

                "message":
                    "No face detected."
            })


        if face_count > 1:

            return jsonify({

                "status":
                    "multiple_faces",

                "face_count":
                    face_count,

                "message":
                    "Multiple faces detected."
            })


        return jsonify({

            "status":
                "present",

            "face_count":
                1,

            "message":
                "One face detected."
        })


    except Exception as e:

        print(

            "Presence Check Error:",

            str(e)
        )


        traceback.print_exc()


        return jsonify({

            "status":
                "error",

            "message":
                str(e)
        })


# =========================================================
# EXAM IDENTITY CHECK
# =========================================================

@app.route(
    "/monitor_exam",
    methods=["POST"]
)
def monitor_exam():

    temp_img_path = None


    try:

        data = request.get_json(
            silent=True
        )


        if not data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "No data received"
            })


        email = data.get(
            "email"
        )


        image_data = data.get(
            "image"
        )


        if not email:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Email missing"
            })


        if not image_data:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Camera image missing"
            })


        image = decode_image(
            image_data
        )


        if image is None:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Invalid camera image"
            })


        face_count = count_exam_faces(
            image
        )


        if face_count == 0:

            print(
                "EXAM MONITOR -> NO FACE ❌"
            )


            return jsonify({

                "status":
                    "no_face",

                "message":
                    "No face detected in camera."
            })


        if face_count > 1:

            print(
                "EXAM MONITOR -> MULTIPLE FACES ❌"
            )


            return jsonify({

                "status":
                    "multiple_faces",

                "face_count":
                    face_count,

                "message":
                    "Multiple faces detected."
            })


        temp_img_path = os.path.abspath(

            os.path.join(

                "exam_temp",

                f"{uuid.uuid4().hex}.jpg"
            )
        )


        saved = cv2.imwrite(

            temp_img_path,

            image
        )


        if not saved:

            return jsonify({

                "status":
                    "error",

                "message":
                    "Exam frame could not be saved."
            })


        print("")
        print(
            "========================================"
        )
        print(
            "EXAM IDENTITY VERIFICATION"
        )
        print(
            "Student ->",
            email
        )
        print(
            "Current Frame ->",
            temp_img_path
        )
        print(
            "========================================"
        )


        current_embedding = (
            create_face_embedding(
                temp_img_path
            )
        )


        print(
            "Exam face embedding created ✅"
        )


        # =================================================
        # COMPARE WITH ALL REGISTERED POSES
        # =================================================

        poses = [

            "Forward",

            "Left",

            "Right",

            "Up",

            "Down"
        ]


        distances = []


        for pose in poses:

            saved_path = os.path.abspath(

                os.path.join(

                    "saved_faces",

                    f"{email}_{pose}.jpg"
                )
            )


            if not os.path.exists(
                saved_path
            ):

                continue


            try:

                registered_embedding = (
                    get_registered_embedding(
                        saved_path
                    )
                )


                distance = (
                    calculate_cosine_distance(

                        current_embedding,

                        registered_embedding
                    )
                )


                distances.append({

                    "pose":
                        pose,

                    "distance":
                        distance
                })


                print(

                    f"{pose} Distance -> "

                    f"{distance:.4f}"
                )


            except Exception as e:

                print(

                    f"{pose} Comparison Error:",

                    str(e)
                )


        if not distances:

            return jsonify({

                "status":
                    "error",

                "message":
                    "No registered face images found."
            })


        best_match = min(

            distances,

            key=lambda item:
                item["distance"]
        )


        best_pose = (
            best_match[
                "pose"
            ]
        )


        best_distance = (
            best_match[
                "distance"
            ]
        )


        print("")
        print(

            "BEST REGISTERED POSE ->",

            best_pose
        )


        print(

            "BEST COSINE DISTANCE ->",

            f"{best_distance:.4f}"
        )


        print(

            "THRESHOLD ->",

            FACE_MATCH_THRESHOLD
        )


        if (
            best_distance
            <=
            FACE_MATCH_THRESHOLD
        ):

            print(
                "EXAM STUDENT VERIFIED ✅"
            )


            return jsonify({

                "status":
                    "success",

                "message":
                    "Student identity verified.",

                "distance":
                    round(
                        best_distance,
                        4
                    ),

                "matched_pose":
                    best_pose,

                "threshold":
                    FACE_MATCH_THRESHOLD
            })


        print(
            "EXAM FACE DOES NOT MATCH ❌"
        )


        return jsonify({

            "status":
                "failed",

            "message":
                "Current person does not match "
                "the registered student.",

            "distance":
                round(
                    best_distance,
                    4
                ),

            "threshold":
                FACE_MATCH_THRESHOLD
        })


    except Exception as e:

        print("")
        print(
            "EXAM MONITOR ERROR ❌"
        )

        traceback.print_exc()

        print("")


        return jsonify({

            "status":
                "error",

            "message":
                str(e)
        })


    finally:

        if (
            temp_img_path
            and
            os.path.exists(
                temp_img_path
            )
        ):

            try:

                os.remove(
                    temp_img_path
                )


            except Exception as e:

                print(

                    "Exam Temp Delete Error:",

                    str(e)
                )


# =========================================================
# HOME
# =========================================================

@app.route(
    "/",
    methods=["GET"]
)
def home():

    return jsonify({

        "status":
            "success",

        "message":
            "Monitor AI Backend Running"
    })


# =========================================================
# SERVER
# =========================================================

if __name__ == "__main__":

    print("")
    print(
        "========================================"
    )
    print(
        "Monitor AI Backend Started ✅"
    )
    print(
        "Registration: "
        "http://127.0.0.1:5000/process_frame"
    )
    print(
        "Login: "
        "http://127.0.0.1:5000/verify_login"
    )
    print(
        "Presence: "
        "http://127.0.0.1:5000/check_exam_presence"
    )
    print(
        "Exam Identity: "
        "http://127.0.0.1:5000/monitor_exam"
    )
    print(
        "========================================"
    )
    print("")


    app.run(

        host=
            "127.0.0.1",

        port=
            5000,

        debug=
            False
    )