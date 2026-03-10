/* ============================================================
   Avidmock SAT Math Scanner — Crop Overlay Tool
   Pure JS crop selection tool with canvas overlay.
   ============================================================ */

(function () {
  'use strict';

  // Prevent double-initialization
  if (window.__avidmockCropOverlay) return;
  window.__avidmockCropOverlay = true;

  const OVERLAY_CLASS = 'avidmock-ext-crop-overlay';
  const CANVAS_CLASS = 'avidmock-ext-crop-canvas';

  class CropOverlay {
    constructor() {
      this.isActive = false;
      this.screenshotImage = null;
      this.overlay = null;
      this.canvas = null;
      this.ctx = null;
      this.startX = 0;
      this.startY = 0;
      this.endX = 0;
      this.endY = 0;
      this.isDragging = false;
      this.isResizing = false;
      this.resizeHandle = null;
      this.hasSelection = false;
      this.moveOffset = { x: 0, y: 0 };
      this.isMoving = false;

      // Bind methods
      this.onMouseDown = this.onMouseDown.bind(this);
      this.onMouseMove = this.onMouseMove.bind(this);
      this.onMouseUp = this.onMouseUp.bind(this);
      this.onKeyDown = this.onKeyDown.bind(this);
    }

    /**
     * Activate the crop overlay with a screenshot image.
     * @param {string} screenshotDataUrl - Full page screenshot as data URL.
     * @returns {Promise<string|null>} Cropped image base64 or null if cancelled.
     */
    activate(screenshotDataUrl) {
      return new Promise((resolve) => {
        this.resolve = resolve;
        this.screenshotImage = new Image();
        this.screenshotImage.onload = () => {
          this.createOverlay();
          this.isActive = true;
        };
        this.screenshotImage.src = screenshotDataUrl;
      });
    }

    createOverlay() {
      // Remove any existing overlay
      this.destroy();

      // Create overlay container
      this.overlay = document.createElement('div');
      this.overlay.className = OVERLAY_CLASS;

      // Create canvas
      this.canvas = document.createElement('canvas');
      this.canvas.className = CANVAS_CLASS;
      this.canvas.width = window.innerWidth;
      this.canvas.height = window.innerHeight;
      this.ctx = this.canvas.getContext('2d');

      this.overlay.appendChild(this.canvas);

      // Instructions
      const instructions = document.createElement('div');
      instructions.className = 'avidmock-ext-crop-instructions';
      instructions.innerHTML =
        'Click and drag to select the math problem &nbsp; <kbd>ESC</kbd> cancel &nbsp; <kbd>Enter</kbd> confirm';
      this.overlay.appendChild(instructions);
      this.instructionsEl = instructions;

      document.body.appendChild(this.overlay);

      // Draw initial state
      this.drawOverlay();

      // Bind events
      this.canvas.addEventListener('mousedown', this.onMouseDown);
      window.addEventListener('mousemove', this.onMouseMove);
      window.addEventListener('mouseup', this.onMouseUp);
      window.addEventListener('keydown', this.onKeyDown);
    }

    drawOverlay() {
      const { ctx, canvas } = this;
      const w = canvas.width;
      const h = canvas.height;

      ctx.clearRect(0, 0, w, h);

      // Draw the screenshot
      ctx.drawImage(this.screenshotImage, 0, 0, w, h);

      // Semi-transparent dark overlay
      ctx.fillStyle = 'rgba(20, 50, 48, 0.6)';
      ctx.fillRect(0, 0, w, h);

      if (this.hasSelection) {
        const rect = this.getSelectionRect();

        // Clear the selected area to show original screenshot
        ctx.clearRect(rect.x, rect.y, rect.w, rect.h);
        ctx.drawImage(
          this.screenshotImage,
          rect.x * (this.screenshotImage.naturalWidth / w),
          rect.y * (this.screenshotImage.naturalHeight / h),
          rect.w * (this.screenshotImage.naturalWidth / w),
          rect.h * (this.screenshotImage.naturalHeight / h),
          rect.x,
          rect.y,
          rect.w,
          rect.h
        );

        // Selection border
        ctx.strokeStyle = '#1fe290';
        ctx.lineWidth = 2;
        ctx.setLineDash([]);
        ctx.strokeRect(rect.x, rect.y, rect.w, rect.h);

        // Corner handles
        this.drawHandles(rect);

        // Dimension label
        this.drawDimensions(rect);
      }
    }

    drawHandles(rect) {
      const { ctx } = this;
      const handleSize = 8;
      const handles = this.getHandlePositions(rect);

      ctx.fillStyle = '#1fe290';
      ctx.strokeStyle = '#143230';
      ctx.lineWidth = 1;

      for (const handle of Object.values(handles)) {
        ctx.beginPath();
        ctx.arc(handle.x, handle.y, handleSize / 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
      }
    }

    getHandlePositions(rect) {
      return {
        nw: { x: rect.x, y: rect.y },
        ne: { x: rect.x + rect.w, y: rect.y },
        sw: { x: rect.x, y: rect.y + rect.h },
        se: { x: rect.x + rect.w, y: rect.y + rect.h },
        n: { x: rect.x + rect.w / 2, y: rect.y },
        s: { x: rect.x + rect.w / 2, y: rect.y + rect.h },
        w: { x: rect.x, y: rect.y + rect.h / 2 },
        e: { x: rect.x + rect.w, y: rect.y + rect.h / 2 },
      };
    }

    drawDimensions(rect) {
      const { ctx } = this;
      const text = `${Math.round(rect.w)} x ${Math.round(rect.h)}`;
      const fontSize = 11;
      ctx.font = `${fontSize}px monospace`;
      const metrics = ctx.measureText(text);
      const padding = 4;
      const bgW = metrics.width + padding * 2;
      const bgH = fontSize + padding * 2;
      const posX = rect.x + rect.w / 2 - bgW / 2;
      const posY = rect.y + rect.h + 8;

      ctx.fillStyle = 'rgba(20, 50, 48, 0.9)';
      ctx.beginPath();
      ctx.roundRect(posX, posY, bgW, bgH, 3);
      ctx.fill();

      ctx.fillStyle = '#9cb8b5';
      ctx.fillText(text, posX + padding, posY + fontSize + padding / 2);
    }

    getSelectionRect() {
      const x = Math.min(this.startX, this.endX);
      const y = Math.min(this.startY, this.endY);
      const w = Math.abs(this.endX - this.startX);
      const h = Math.abs(this.endY - this.startY);
      return { x, y, w, h };
    }

    hitTestHandle(mouseX, mouseY) {
      if (!this.hasSelection) return null;
      const rect = this.getSelectionRect();
      const handles = this.getHandlePositions(rect);
      const threshold = 10;

      for (const [key, pos] of Object.entries(handles)) {
        const dist = Math.sqrt((mouseX - pos.x) ** 2 + (mouseY - pos.y) ** 2);
        if (dist <= threshold) return key;
      }
      return null;
    }

    isInsideSelection(mouseX, mouseY) {
      if (!this.hasSelection) return false;
      const rect = this.getSelectionRect();
      return (
        mouseX >= rect.x &&
        mouseX <= rect.x + rect.w &&
        mouseY >= rect.y &&
        mouseY <= rect.y + rect.h
      );
    }

    onMouseDown(e) {
      const mouseX = e.clientX;
      const mouseY = e.clientY;

      // Check if clicking on a handle
      const handle = this.hitTestHandle(mouseX, mouseY);
      if (handle) {
        this.isResizing = true;
        this.resizeHandle = handle;
        return;
      }

      // Check if clicking inside selection (to move)
      if (this.isInsideSelection(mouseX, mouseY)) {
        this.isMoving = true;
        const rect = this.getSelectionRect();
        this.moveOffset = {
          x: mouseX - rect.x,
          y: mouseY - rect.y,
        };
        return;
      }

      // Start new selection
      this.isDragging = true;
      this.startX = mouseX;
      this.startY = mouseY;
      this.endX = mouseX;
      this.endY = mouseY;
      this.hasSelection = false;
      this.removeActions();
    }

    onMouseMove(e) {
      const mouseX = e.clientX;
      const mouseY = e.clientY;

      if (this.isDragging) {
        this.endX = mouseX;
        this.endY = mouseY;
        this.hasSelection = true;
        this.drawOverlay();
        return;
      }

      if (this.isResizing) {
        this.resizeSelection(mouseX, mouseY);
        this.drawOverlay();
        return;
      }

      if (this.isMoving) {
        const rect = this.getSelectionRect();
        const newX = mouseX - this.moveOffset.x;
        const newY = mouseY - this.moveOffset.y;
        const dx = newX - Math.min(this.startX, this.endX);
        const dy = newY - Math.min(this.startY, this.endY);
        this.startX += dx;
        this.endX += dx;
        this.startY += dy;
        this.endY += dy;
        this.drawOverlay();
        return;
      }

      // Update cursor
      if (this.hasSelection) {
        const handle = this.hitTestHandle(mouseX, mouseY);
        if (handle) {
          const cursors = {
            nw: 'nw-resize', ne: 'ne-resize', sw: 'sw-resize', se: 'se-resize',
            n: 'n-resize', s: 's-resize', w: 'w-resize', e: 'e-resize',
          };
          this.canvas.style.cursor = cursors[handle] || 'crosshair';
        } else if (this.isInsideSelection(mouseX, mouseY)) {
          this.canvas.style.cursor = 'move';
        } else {
          this.canvas.style.cursor = 'crosshair';
        }
      }
    }

    resizeSelection(mouseX, mouseY) {
      const handle = this.resizeHandle;
      // Ensure startX/startY is always the top-left for resize logic
      let rect = this.getSelectionRect();

      if (handle.includes('w')) {
        this.startX = mouseX;
        if (this.endX < this.startX) this.endX = rect.x + rect.w;
      }
      if (handle.includes('e')) {
        this.endX = mouseX;
      }
      if (handle.includes('n')) {
        this.startY = mouseY;
        if (this.endY < this.startY) this.endY = rect.y + rect.h;
      }
      if (handle.includes('s')) {
        this.endY = mouseY;
      }
    }

    onMouseUp(e) {
      if (this.isDragging || this.isResizing || this.isMoving) {
        this.isDragging = false;
        this.isResizing = false;
        this.isMoving = false;

        const rect = this.getSelectionRect();
        if (rect.w > 10 && rect.h > 10) {
          this.hasSelection = true;
          this.showActions(rect);
        }
      }
    }

    showActions(rect) {
      this.removeActions();

      const container = document.createElement('div');
      container.className = 'avidmock-ext-crop-actions';
      container.style.left = `${rect.x + rect.w / 2 - 100}px`;
      container.style.top = `${rect.y + rect.h + 16}px`;

      const btnScan = document.createElement('button');
      btnScan.className = 'avidmock-ext-btn avidmock-ext-btn-primary';
      btnScan.textContent = 'Scan Selection';
      btnScan.addEventListener('click', () => this.confirmCrop());

      const btnCancel = document.createElement('button');
      btnCancel.className = 'avidmock-ext-btn avidmock-ext-btn-secondary';
      btnCancel.textContent = 'Cancel';
      btnCancel.addEventListener('click', () => this.cancel());

      container.appendChild(btnScan);
      container.appendChild(btnCancel);
      this.overlay.appendChild(container);
      this.actionsEl = container;
    }

    removeActions() {
      if (this.actionsEl) {
        this.actionsEl.remove();
        this.actionsEl = null;
      }
    }

    onKeyDown(e) {
      if (!this.isActive) return;

      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        this.cancel();
      }

      if (e.key === 'Enter' && this.hasSelection) {
        e.preventDefault();
        e.stopPropagation();
        this.confirmCrop();
      }
    }

    confirmCrop() {
      if (!this.hasSelection) return;

      const rect = this.getSelectionRect();
      const base64 = this.cropImage(rect);
      this.destroy();
      this.resolve(base64);
    }

    cancel() {
      this.destroy();
      this.resolve(null);
    }

    cropImage(rect) {
      const tempCanvas = document.createElement('canvas');
      const dpr = window.devicePixelRatio || 1;

      // Map selection coordinates to the screenshot's natural dimensions
      const scaleX = this.screenshotImage.naturalWidth / this.canvas.width;
      const scaleY = this.screenshotImage.naturalHeight / this.canvas.height;

      const srcX = rect.x * scaleX;
      const srcY = rect.y * scaleY;
      const srcW = rect.w * scaleX;
      const srcH = rect.h * scaleY;

      tempCanvas.width = srcW;
      tempCanvas.height = srcH;

      const tempCtx = tempCanvas.getContext('2d');
      tempCtx.drawImage(
        this.screenshotImage,
        srcX,
        srcY,
        srcW,
        srcH,
        0,
        0,
        srcW,
        srcH
      );

      // Return base64 without data URL prefix
      const dataUrl = tempCanvas.toDataURL('image/png');
      return dataUrl.split(',')[1];
    }

    destroy() {
      this.isActive = false;
      this.hasSelection = false;
      this.isDragging = false;
      this.isResizing = false;
      this.isMoving = false;

      window.removeEventListener('mousemove', this.onMouseMove);
      window.removeEventListener('mouseup', this.onMouseUp);
      window.removeEventListener('keydown', this.onKeyDown);

      if (this.overlay) {
        this.overlay.remove();
        this.overlay = null;
      }

      this.canvas = null;
      this.ctx = null;
    }
  }

  // Expose globally for content.js
  window.__AvidmockCropOverlay = CropOverlay;
})();
