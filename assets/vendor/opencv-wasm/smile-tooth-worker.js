let openCvPromise = null;

function loadOpenCv() {
  if (openCvPromise) return openCvPromise;
  openCvPromise = new Promise(function (resolve, reject) {
    try {
      importScripts('./opencv.js');
      if (self.cv && typeof self.cv.Mat === 'function') {
        resolve(self.cv);
        return;
      }
      const deadline = Date.now() + 60000;
      const poll = self.setInterval(function () {
        if (self.cv && typeof self.cv.Mat === 'function') {
          self.clearInterval(poll);
          resolve(self.cv);
          return;
        }
        if (Date.now() >= deadline) {
          self.clearInterval(poll);
          reject(new Error('OpenCV initialization timed out.'));
        }
      }, 50);
    } catch (error) {
      reject(error);
    }
  });
  return openCvPromise;
}

function nearestSeed(mask, width, height, targetX, targetY, radius) {
  let best = null;
  let bestDistance = Number.POSITIVE_INFINITY;
  for (let y = Math.max(0, targetY - radius); y <= Math.min(height - 1, targetY + radius); y += 1) {
    for (let x = Math.max(0, targetX - radius); x <= Math.min(width - 1, targetX + radius); x += 1) {
      if (!mask[(y * width) + x]) continue;
      const distance = ((x - targetX) * (x - targetX)) + ((y - targetY) * (y - targetY));
      if (distance < bestDistance) {
        bestDistance = distance;
        best = { x: x, y: y };
      }
    }
  }
  return best;
}

function contourForMask(cv, binaryMask, width, height) {
  const source = cv.Mat.zeros(height, width, cv.CV_8UC1);
  const contours = new cv.MatVector();
  const hierarchy = new cv.Mat();
  let simplified = null;
  try {
    for (let index = 0; index < binaryMask.length; index += 1) {
      source.data[index] = binaryMask[index] ? 255 : 0;
    }
    cv.findContours(source, contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_NONE);
    if (!contours.size()) return [];
    let bestIndex = 0;
    let bestArea = 0;
    for (let index = 0; index < contours.size(); index += 1) {
      const candidate = contours.get(index);
      const area = Math.abs(cv.contourArea(candidate, false));
      candidate.delete();
      if (area > bestArea) {
        bestArea = area;
        bestIndex = index;
      }
    }
    const contour = contours.get(bestIndex);
    simplified = new cv.Mat();
    const perimeter = cv.arcLength(contour, true);
    cv.approxPolyDP(contour, simplified, Math.max(0.30, perimeter * 0.0009), true);
    contour.delete();
    const points = [];
    for (let index = 0; index < simplified.data32S.length; index += 2) {
      points.push({
        x: (simplified.data32S[index] / Math.max(1, width)) * 100,
        y: (simplified.data32S[index + 1] / Math.max(1, height)) * 100
      });
    }
    return points.length >= 8 ? points : [];
  } finally {
    source.delete();
    contours.delete();
    hierarchy.delete();
    if (simplified) simplified.delete();
  }
}

function segmentTeeth(cv, request) {
  const width = request.width;
  const height = request.height;
  const upperBand = request.upperBand;
  const boundaries = request.boundaries;
  const teeth = request.teeth;
  const hardMask = request.hardMask;
  const softMask = request.softMask;
  const rgba = new cv.Mat(height, width, cv.CV_8UC4);
  const rgb = new cv.Mat();
  const markers = cv.Mat.ones(height, width, cv.CV_32SC1);
  try {
    rgba.data.set(request.imageData);
    cv.cvtColor(rgba, rgb, cv.COLOR_RGBA2RGB);
    for (let y = upperBand.minY; y <= upperBand.maxY; y += 1) {
      for (let x = upperBand.minX; x <= upperBand.maxX; x += 1) {
        const index = (y * width) + x;
        if (softMask[index]) markers.data32S[index] = 0;
      }
    }

    const seeds = [];
    teeth.forEach(function (toothNumber, slotIndex) {
      const left = Math.round(boundaries[slotIndex]);
      const right = Math.round(boundaries[slotIndex + 1]);
      const targetX = Math.round((left + right) / 2);
      const targetY = Math.round(upperBand.minY + ((upperBand.maxY - upperBand.minY) * 0.48));
      const seed = nearestSeed(hardMask, width, height, targetX, targetY, Math.max(5, Math.round((right - left) * 0.55)));
      if (!seed || seed.x < left - 2 || seed.x > right + 2) return;
      const label = slotIndex + 2;
      const radiusX = Math.max(1, Math.round((right - left) * 0.09));
      const radiusY = Math.max(1, Math.round((upperBand.maxY - upperBand.minY) * 0.07));
      for (let y = Math.max(upperBand.minY, seed.y - radiusY); y <= Math.min(upperBand.maxY, seed.y + radiusY); y += 1) {
        for (let x = Math.max(left, seed.x - radiusX); x <= Math.min(right, seed.x + radiusX); x += 1) {
          const index = (y * width) + x;
          if (hardMask[index]) markers.data32S[index] = label;
        }
      }
      markers.data32S[(seed.y * width) + seed.x] = label;
      seeds.push({ toothNumber: toothNumber, label: label });
    });

    if (seeds.length < 8) return {};
    cv.watershed(rgb, markers);
    const contours = {};
    seeds.forEach(function (seed) {
      const toothMask = new Uint8Array(width * height);
      let count = 0;
      for (let y = upperBand.minY; y <= upperBand.maxY; y += 1) {
        for (let x = upperBand.minX; x <= upperBand.maxX; x += 1) {
          const index = (y * width) + x;
          if (markers.data32S[index] !== seed.label || !softMask[index]) continue;
          toothMask[index] = 1;
          count += 1;
        }
      }
      const contour = count >= 10 ? contourForMask(cv, toothMask, width, height) : [];
      if (contour.length >= 8) contours[seed.toothNumber] = { contour: contour, count: count };
    });
    return contours;
  } finally {
    rgba.delete();
    rgb.delete();
    markers.delete();
  }
}

self.addEventListener('message', async function (event) {
  const request = event.data || {};
  try {
    const cv = await loadOpenCv();
    const contours = segmentTeeth(cv, request);
    self.postMessage({ key: request.key, contours: contours });
  } catch (error) {
    self.postMessage({ key: request.key, contours: {}, error: String(error && error.message ? error.message : error) });
  }
});
