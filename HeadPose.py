import cv2
import mediapipe as mp
import time

mp_face_mesh = mp.solutions.face_mesh

NOSE_TIP = 1
LEFT_EYE = 33
RIGHT_EYE = 263

AWAY_THRESHOLD_SEC = 5

_face_mesh = mp_face_mesh.FaceMesh(
    max_num_faces=3,
    refine_landmarks=False,
    min_detection_confidence=0.5,
    min_tracking_confidence=0.5,
)

_looking_away_since = None


def get_head_pose(frame):
    global _looking_away_since

    h, w = frame.shape[:2]
    rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    res = _face_mesh.process(rgb)

    face_count = 0
    direction = "FORWARD"
    looking_away = False
    alert = None
    away_seconds = 0.0

    if res.multi_face_landmarks:
        face_count = len(res.multi_face_landmarks)
        lm = res.multi_face_landmarks[0].landmark

        nx, ny = int(lm[NOSE_TIP].x * w), int(lm[NOSE_TIP].y * h)
        lx, ly = int(lm[LEFT_EYE].x * w), int(lm[LEFT_EYE].y * h)
        rx, ry = int(lm[RIGHT_EYE].x * w), int(lm[RIGHT_EYE].y * h)

        eye_center_x = (lx + rx) // 2
        eye_center_y = (ly + ry) // 2

        dx = nx - eye_center_x
        dy = ny - eye_center_y

        LEFT_THRESHOLD = -20
        RIGHT_THRESHOLD = 20
        DOWN_THRESHOLD = 60

        if dx < LEFT_THRESHOLD:
            direction = "LEFT"
        elif dx > RIGHT_THRESHOLD:
            direction = "RIGHT"
        elif dy > DOWN_THRESHOLD:
            direction = "DOWN"
        else:
            direction = "FORWARD"

        if direction != "FORWARD":
            looking_away = True

            if _looking_away_since is None:
                _looking_away_since = time.time()

            away_seconds = time.time() - _looking_away_since

            if away_seconds > AWAY_THRESHOLD_SEC:
                alert = "looking_away"

        else:
            _looking_away_since = None

    return {
        "face_count": face_count,
        "direction": direction,
        "looking_away": looking_away,
        "away_seconds": round(away_seconds, 1),
        "alert": alert,
    }