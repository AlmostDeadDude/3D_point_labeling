# 3D Point Labeling Demo

This is a static demo of a browser-based interface for labeling 3D point clouds. It was originally built at the Institute for Photogrammetry (ifp), University of Stuttgart, for crowdsourced annotation experiments. The demo keeps the look and interaction of the production tool but runs read-only = no responses or analytics are stored.

## What you see

-   WebGL canvas (Three.js + PCD loader) showing a highlighted point from a sample job (`Data_AL/job1/*`).
-   Class choices rendered as large buttons with icon previews.
-   Tabs for the task, a short reference video, and an About section describing the research context.
-   A generated proof code to mirror the original completion flow (now just illustrative).

## How it behaves in demo mode

-   Default IDs are injected so the page loads without query params.
-   All write paths (results, time, visits, feedback) are disabled; feedback endpoints short-circuit.
-   Quality checks remain visible in the code but are effectively off for the demo.
