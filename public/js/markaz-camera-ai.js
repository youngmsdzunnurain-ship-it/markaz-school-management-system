import {
    FilesetResolver,
    FaceLandmarker,
    PoseLandmarker
} from "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@1.0.1/+esm";

const VISION_WASM_URL =
    "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@1.0.1/wasm";

const FACE_MODEL_URL =
    "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task";

const POSE_MODEL_URL =
    "https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task";

window.MarkazCameraAI = {
    FilesetResolver,
    FaceLandmarker,
    PoseLandmarker,
    VISION_WASM_URL,
    FACE_MODEL_URL,
    POSE_MODEL_URL
};

window.dispatchEvent(new CustomEvent("markazCameraAIReady"));

console.log("MARKAZ Camera AI library loaded.");

window.MarkazCameraAI.initialize = async function () {
    if (window.MarkazCameraAI.faceLandmarker &&
        window.MarkazCameraAI.poseLandmarker) {
        return window.MarkazCameraAI;
    }

    const vision = await FilesetResolver.forVisionTasks(
        window.MarkazCameraAI.VISION_WASM_URL
    );

    const faceLandmarker = await FaceLandmarker.createFromOptions(
        vision,
        {
            baseOptions: {
                modelAssetPath: window.MarkazCameraAI.FACE_MODEL_URL,
                delegate: "GPU"
            },
            runningMode: "VIDEO",
            numFaces: 1,
            minFaceDetectionConfidence: 0.5,
            minFacePresenceConfidence: 0.5,
            minTrackingConfidence: 0.5
        }
    );

    const poseLandmarker = await PoseLandmarker.createFromOptions(
        vision,
        {
            baseOptions: {
                modelAssetPath: window.MarkazCameraAI.POSE_MODEL_URL,
                delegate: "GPU"
            },
            runningMode: "VIDEO",
            numPoses: 1,
            minPoseDetectionConfidence: 0.5,
            minPosePresenceConfidence: 0.5,
            minTrackingConfidence: 0.5
        }
    );

    window.MarkazCameraAI.faceLandmarker = faceLandmarker;
    window.MarkazCameraAI.poseLandmarker = poseLandmarker;

    console.log("MARKAZ Camera AI initialized.");

    return window.MarkazCameraAI;
};

window.MarkazCameraAI.initialize = async function () {
    if (window.MarkazCameraAI.faceLandmarker &&
        window.MarkazCameraAI.poseLandmarker) {
        return window.MarkazCameraAI;
    }

    const vision = await FilesetResolver.forVisionTasks(
        window.MarkazCameraAI.VISION_WASM_URL
    );

    const faceLandmarker = await FaceLandmarker.createFromOptions(
        vision,
        {
            baseOptions: {
                modelAssetPath: window.MarkazCameraAI.FACE_MODEL_URL,
                delegate: "GPU"
            },
            runningMode: "VIDEO",
            numFaces: 1,
            minFaceDetectionConfidence: 0.5,
            minFacePresenceConfidence: 0.5,
            minTrackingConfidence: 0.5
        }
    );

    const poseLandmarker = await PoseLandmarker.createFromOptions(
        vision,
        {
            baseOptions: {
                modelAssetPath: window.MarkazCameraAI.POSE_MODEL_URL,
                delegate: "GPU"
            },
            runningMode: "VIDEO",
            numPoses: 1,
            minPoseDetectionConfidence: 0.5,
            minPosePresenceConfidence: 0.5,
            minTrackingConfidence: 0.5
        }
    );

    window.MarkazCameraAI.faceLandmarker = faceLandmarker;
    window.MarkazCameraAI.poseLandmarker = poseLandmarker;

    console.log("MARKAZ Camera AI initialized.");

    return window.MarkazCameraAI;
};
