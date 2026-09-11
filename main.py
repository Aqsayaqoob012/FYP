import cv2
from ultralytics import YOLO
from HeadPose import get_head_pose  # IMPORT

model = YOLO("best.pt")

cap = cv2.VideoCapture(0)

while True:
    ret, frame = cap.read()
    if not ret:
        break

    frame = cv2.flip(frame, 1)

    # ================= YOLO =================
    results = model(frame)

    for r in results:
        for box in r.boxes:
            cls = int(box.cls[0])
            conf = float(box.conf[0])
            x1, y1, x2, y2 = map(int, box.xyxy[0])

            label = "Unknown"

            if cls == 0:
                label = "Phone"
            elif cls == 1:
                label = "Person"

            cv2.rectangle(frame, (x1,y1), (x2,y2), (0,255,0), 2)
            cv2.putText(frame, f"{label} {conf:.2f}", (x1,y1-10),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0,255,0), 2)

    # ================= HEAD POSE =================
    pose = get_head_pose(frame)

    text = f"{pose['direction']} | Away: {pose['away_seconds']}s"
    cv2.putText(frame, text, (20,50),
                cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0,0,255), 2)

    if pose["alert"]:
        cv2.putText(frame, "ALERT: LOOKING AWAY!", (20,100),
                    cv2.FONT_HERSHEY_SIMPLEX, 1, (0,0,255), 3)

    # ================= SHOW =================
    cv2.imshow("Cheating Guard AI", frame)

    if cv2.waitKey(1) & 0xFF == 27:
        break

cap.release()
cv2.destroyAllWindows()