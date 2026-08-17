// // Initialize text editor functionality once document is ready
// document.addEventListener('DOMContentLoaded', () => {
//     new TextEditor({
//         uploadUrl: "http://localhost/pressli/admin/media/upload"
//     });
// });

/**
 * Text Editor class that handles text editing
 * 
 * This is the main class that creates the text editor interface, adds all event listeners
 * and handles all user actions related to creating and editing content in the text editor.
 */
class TextEditor {

    /**
     * Main content editor container
     */
    container;

    /**
     * Toolbar with editor buttons
     */
    toolbar;

    /**
     * Main content editor <div>
     */
    editor;

    /**
     * Media upload URL
     */
    uploadUrl;

    /**
     * Media import URL
     */
    importUrl;

    /** 
     * CSRF token
     */
    CSRF = '';

    /**
     * Constructor initializes the content editable area and toolbar, then 
     * it creates and assigns all event listeners on the text editor buttons
     */
    constructor(config = {}) {

        // Grab the user content editor div
        this.editor = document.querySelector('.text-editor-div');
        if(!this.editor) return;

        // Create and inject the CSS styles
        const css   = document.createElement('style');
        
        css.textContent = this.styles;
        document.head.append(css);

        // Set the configuration provider
        if(config?.uploadUrl) this.uploadUrl = config.uploadUrl;
        if(config?.CSRF) this.CSRF   = config.CSRF;

        // Configure editor container
        this.editor.contentEditable     = "true";
        this.editor.dataset.placeholder = "What's on your mind?";
        this.container  = document.createElement('div');
        this.container.className   = 'text-editor-container';
        
        this.container.innerHTML    = this.markup;        
        this.editor.parentNode.insertBefore(this.container, this.editor)

        this.container.append(this.editor);
        this.toolbar    = this.container.querySelector('.text-editor-toolbar');

        // Set default paragraph separator to <p>
        document.execCommand('defaultParagraphSeparator', false, 'p');
        const buttons   = this.toolbar.querySelectorAll(':scope > span');
        
        // Set button event listeners for text editing        
        buttons.forEach((button) => {

            let handler = button.id.replace('text-editor-', '');

            if(handler == 'html' || handler == 'text') {
               
                button.onclick = (event) => {
                    
                    if(typeof this[handler] === 'function') {
                        this[handler](event);
                    }
                    else console.log(`No handler for ${handler}`);
                }  
                return;
            }

            button.onmousedown = (event) => {

                // Prevent moving focus away from selection
                event.preventDefault();

                if(typeof this[handler] === 'function') {
                    this[handler](event);
                }
                else console.log(`No handler for ${handler}`);
            }             
        });

        // Initialize text editor and set event listeners
        this.initEditor();
        this.editor.onkeyup = (event) => this.initEditor(event);
        this.editor.onfocus = (event) => this.initEditor(event);
        this.editor.onpaste = (event) => this.onPaste(event);

        // Initialize click handler for images
        this.editor.onclick = (event) => {

            let target   = event.target;

            // Handle if it's a click on an image
            if(target.tagName === 'IMG') {

                event.stopPropagation();
                this.showImageToolbar(target);
            }
            else this.removeImageToolbar();
        }
    }

    /**
     * Initialize the first paragraph by creating an empty <p><br></p> so that the
     * cursor can focus on it and start blinking. If editor is not empty focus on start
     */
    initEditor(event = null) {

        // Return if it's not a delete event
        if(event && event.type == 'keyup') {    
            if((event.key !== 'Backspace' && event.key !== 'Delete')) return;      
        }   

        // If editor has content, just focus() and return
        if(this.editor.innerHTML.trim().length) {
            
            this.editor.focus();
            return;        
        }
        
        // Inject a paragraph with a <br> to hold the cursor
        this.editor.innerHTML     = '<p><br></p>'; 
        this.editor.focus();
        
        let selection   = window.getSelection();
        let range       = new Range();
        
        range.setStart(this.editor.firstChild, 0);
        range.collapse(true);

        selection.removeAllRanges();
        selection.addRange(range, 0);
    } 

    /**
     * Handles paste events in the editor.
     * 
     * Takes pasted content from other sources, strips of any html styling and formating before
     * adding the content to the editor. Helps ensure consistency in display in the front end.
     * 
     * @param event 
     * @returns 
     */
    onPaste(event) {

        // Prevent default browser paste action
        event.preventDefault();

        // Get HTML string from the clipboard
        let htmlData    = event.clipboardData.getData('text/html');
        let textData    = event.clipboardData.getData('text/plain');

        // Process messy HTML into clear HTML
        if(htmlData) {

            let parser   = new DOMParser();
            let doc = parser.parseFromString(htmlData, 'text/html');

            // Special handling of Google Docs
            let metas;
            metas   = doc.querySelectorAll('meta, br.Apple-interchange-newline');
            metas.forEach(element => element.remove());

            metas   = doc.querySelectorAll('b[id^="docs-internal-guid-"]');
            metas.forEach(element => element.replaceWith(...element.childNodes));

            // Process images inside the pasted content
            // let images  = doc.querySelectorAll('img');
            
            // for(let img of images) {

            //     let src = img.getAttribute('src');
            //     if(!src || !src.includes('googleusercontent.com') || !src.startsWith('blob:')) continue;

            //     try {

            //         // Fetch the raw binary data directly from the browser
            //         let response    = await fetch(src);
            //         let blog    = await response.blob();

            //         // Convert blob into a base64 string
            //         let base64image   = await new Promise((resolve) => {
            //             let reader  = new FileReader();
            //             reader.onloadend = () => resolve(reader.result);
            //             reader.readAsDataURL(blob);
            //         });

            //         // Swap out the link with the base64 code
            //         img.setAttribute('src', base64image);
            //     }
            //     catch(error) {

            //         // If it fails, log the error message and remove the image
            //         console.error(error);
            //         img.remove();
            //     }
            // }

            // Convert structural inline styles to semantic elements
            function translateStyles(node) {

                if(node.nodeType !== Node.ELEMENT_NODE) return;

                // Process deep elements first - inside out traversal
                Array.from(node.childNodes).forEach(translateStyles);

                let style   = node.style;
                if(!style) return;

                let wrapper = node;

                // Detect bold formating
                if(style.fontWeight === '700' || style.fontWeight === 'bold' || style.fontWeight === 'bolder') {

                    let strong  = document.createElement('strong');

                    wrapper.replaceWith(strong);
                    strong.appendChild(wrapper);
                    wrapper = strong;
                }

                // Detect Italics
                if(style.fontStyle === 'italic') {

                    let italic  = document.createElement('em');

                    wrapper.replaceWith(italic);
                    italic.appendChild(wrapper);
                    wrapper = italic;
                }
            }

            // Strip all junk attributes and unwrap empty spans
            function cleanStructure(node) {

                if(node.nodeType !== Node.ELEMENT_NODE) return;

                // Inside out traversal
                Array.from(node.childNodes).forEach(cleanStructure);

                let tagName = node.tagName;

                // Handle links 
                if(tagName === 'A') {

                    let href    = node.getAttribute('href');               

                    // Strip everything except the valid ahref
                    Array.from(node.attributes).forEach(attr => node.removeAttribute(attr.name));

                    if(href) node.setAttribute('href', href);
                    node.setAttribute('target', '_blank');

                    return;
                }

                // Handle images
                if(tagName === 'IMG') {

                    let src = node.getAttribute('src');
                    let alt = node.getAttribute('alt');

                    Array.from(node.attributes).forEach(attr => node.removeAttribute(attr.name));

                    if(src) node.setAttribute('src', src);
                    if(alt) node.setAttribute('alt', alt);

                    return;
                }

                // Unwrap paragraphs trapped inside a list item
                if(tagName === 'P' && node.parentNode && (node.parentNode.tagName === 'LI')) {

                    node.replaceWith(...node.childNodes);
                    return;
                }

                // Unwrap completely useless spans or empty Google GUID containers
                if(tagName === 'SPAN') {

                    // if(!node.hasChildNodes() || node.textContent.trim() === '') node.remove();
                    if(!node.innerHTML.trim()) node.remove();
                    else node.replaceWith(...node.childNodes);

                    return;
                }

                // For all other elements like h1, h2, p etc remove all style/class/id attributes
                Array.from(node.attributes).forEach(attr => node.removeAttribute(attr.name));
            }

            // Run the cleanup functions
            translateStyles(doc.body);
            cleanStructure(doc.body);

            textData    = doc.body.innerHTML;
        }

        // Insert the text into the editor
        let selection   = document.getSelection();
        if(!selection.rangeCount) return;

        let range   = selection.getRangeAt(0);

        // Delete highlighted text if any
        range.deleteContents();

        // Create a temporaty fragment to host the new nodes
        let template    = document.createElement('template');
        template.innerHTML = textData.trim();
        let fragment    = template.content;

        // Insert and update selection anchor/focus
        range.insertNode(fragment);
        selection.collapseToEnd();

        // Upload pasted base64 images if present
        let imagesToUpload  = this.editor.querySelectorAll('img[src^="data:"]');

        imagesToUpload.forEach((imgNode, index) => {

            // Pull the closest headline to use as image name
            let imageName  = this.createImageName(imgNode);
            imageName  = imageName.replace(/[^a-zA-Z0-9]+/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 50);

            // Upload image to server
            this.uploadImage(imgNode, imageName);
        });
    }

    /**
     * Displays image toolbar for managing images.
     * 
     */
    showImageToolbar(imageNode) {

        // Clear any existing toolbars first
        this.removeImageToolbar();

        let toolbar       = document.createElement('div');
        toolbar.className = 'text-editor-image-toolbar';

        // Determine if image is a base64 that needs uploading
        let isBase64    = imageNode.src.startsWith('data:');

        // Check if image is external
        // let isExternal  = !imageNode.src.startsWith('/') && !imageNode.src.startsWith('data:') && new URL(imageNode.src).hostname !== window.location.hostname;

        let toolbarBtns  = `
            <button data-action="left">Left</button>
            <button data-action="center">Center</button>
            <button data-action="right">Right</button>
            <button data-action="delete" style="color:red;">Delete</button>`;
        if(isBase64) {
            toolbarBtns += `
            <button data-action="upload" style="color:blue; font-weight:bold;">Click to Upload</button>`;
        }

        toolbar.innerHTML   = toolbarBtns;

        // Position the toolbar on the image
        let rect    = imageNode.getBoundingClientRect();
        toolbar.style.cssText = `
            position: fixed;
            top: ${rect.top};
            left: ${rect.left};
            z-index: 99;
            background: white;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 4px;
            display: flex;
            gap: 4px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        `;

        document.body.appendChild(toolbar);
        this.activeImageToolbar = toolbar;

        // Handle toolbar button actions
        toolbar.addEventListener('click', async (event) => {

            let action  = event.target.dataset.action;
            if(!action) return;

            if(action === 'delete') {

                imageNode.remove();
                this.removeImageToolbar();
            }
            else if(action === 'upload') {

                event.target.innerHTML = "Uploading...";

                let imageName  = this.createImageName(imgNode);
                imageName  = imageName.replace(/[^a-zA-Z0-9]+/g, ' ').replace(/\s+/g, ' ').substring(0, 50);

                await this.uploadImage(imageNode, imageName);

                // Refresh the toolbar state
                this.showImageToolbar(imageNode);
            }
            else if(action === 'import') {

                event.target.innerHTML = "Importing...";

                let imageName  = this.createImageName(imgNode);
                imageName  = imageName.replace(/[^a-zA-Z0-9]+/g, ' ').replace(/\s+/g, ' ').substring(0, 50);

                await this.importImage(imageNode, imageName);

                // Refresh the toolbar state
                this.showImageToolbar(imageNode);
            }
            else {

                // Set alignment styling classes
                imageNode.className = `text-editor-image-${action}`;
                this.removeImageToolbar();
            }
        });
    }

    /**
     * Removes the image toolbar from DOM
     */
    removeImageToolbar() {

        if(this.activeImageToolbar) {

            this.activeImageToolbar.remove();
            this.activeImageToolbar = null;
        }
    }

    async uploadImage(imageNode, imageName = '') {

        let imageData   = imageNode.src;
        imageData       = this.base64ToBlob(imageData);

        // Extract and append the file extention to the image name
        if(imageName)  imageName = `${imageName}.${imageData.type.split('/')[1]}`;

        // Upload image and log errors on failure
        try {

            // Throw error if there's no upload URL configured
            if(!this.uploadUrl) throw new Error("Upload URL not provided");

            let form = new FormData();
            form.append('file', imageData, imageName);

            // Gray out the image during upload
            imageNode.style.cssText = 'opacity:0.2;';

            let response    = await fetch(this.uploadUrl, {

                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.CSRF
                },
                body: form
            });

            if(!response.ok) throw new Error(response.statusText);
            let data    = await response.json();

            // Swap out the URL on success
            if(data.success) {

                imageNode.setAttribute('src', data.image_url);
                imageNode.setAttribute('alt', data.filename);

                // Remove the opacity filter
                imageNode.style.cssText = '';
                // this.displayUploadStatus(imageNode, true);
            }
            else { 

                // this.displayUploadStatus(imageNode, false);
                throw new Error("Invalid response.");
            }
        }
        catch(error) { console.error(error); }
    }

    async importImage(imageNode, imageName = '') {

        if(!this.importUrl) return;

        try {

            let response    = await fetch(this.importUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF
                },
                body: { 
                    imageUrl: imageNode.src,
                    imageName: imageName 
                }
            });

            let data    = await response.json();
            if(!data.ok) throw new Error(data.statusText);

            if(data.url) {

                imageNode.setAttribute('src', data.url);
                this.showImageToolbar(imageNode);
            }
            else throw new Error("Invalid response.");
        }
        catch(error) { console.error(error); }
    }

    createImageName(imageNode) {

        // Get the main block container
        let currentNode = imageNode.closest('p, div, li') || imageNode;
        let siblings    = [];
        let element     = currentNode.previousSibling;

        // Add all previous siblings together
        while(element) {

            siblings.push(element);
            element = element.previousSibling;
        }

        // Score and filter candidates based on priority
        let bestCandidate= null;
        let bestPriority = 0;

        for(let node of siblings) {

            let text    = node.textContent ? node.textContent : '';
            if(text.length === 0) continue;

            let priorityScore   = 0;

            // Element node check
            if(node.nodeType === node.ELEMENT_NODE) {

                if(node.matches('h1, h2, h3, h4, h5')) priorityScore = 4;
                else if(node.matches('string, b')) priorityScore = 3;
                else if(node.matches('p')) priorityScore = 2;
            }
            else if(node.nodeType === node.TEXT_NODE) priorityScore = 1;

            // If this is the highest priority so far, keep it
            if(priorityScore > bestPriority) {

                bestPriority    = priorityScore;
                bestCandidate   = text;

                if(bestPriority === 4) break;
            }
        }

        return bestCandidate || 'untitled-image';
    }

    base64ToBlob(base64) {

        // 1. Split the header(e.g. data:image/jpeg;base64) from the actual content
        let parts   = base64.split(';base64,');
        let contentType = parts[0].split(':')[1];

        // 2. Convert the base64 string into a raw binary string
        let bytesRaw   = atob(parts[1]);

        // 3. Convert the raw binary characters into an array of numbers
        let bytesNumbers = new Array(bytesRaw.length);

        for(let i = 0; i < bytesNumbers.length; i++) {

            bytesNumbers[i] = bytesRaw.charCodeAt(i);
        }

        // 4. Package the numbers into an unsigned 8 bit integer array
        let bytesArray   = new Uint8Array(bytesNumbers);

        // 5. Return a clean native binary blob object
        return new Blob([bytesArray], { type: contentType });
    }

    // Displays the status badge of the image upload
    displayUploadStatus(imageNode, isSuccess) {

        // Create and append the badge element
        let badge   = document.createElement('div');

        badge.className  = `text-editor-upload-badge ${isSuccess ? 'success' : 'error'}`;
        badge.innerHTML  = isSuccess ? '√' : 'ⅹ';

        // document.body.appendChild(badge);

        // Get the image boundaries
        let rect    = imageNode.getBoundingClientRect();
        let size    = 24;
        let padding = 8;

        let bottom  = rect.bottom; // + window.scrollY;
        let right   = rect.right; // + window.scrollX;

        badge.top   = `${bottom - size - padding}px`;
        badge.left  = `${right - size - padding}px`;

        // this.editor.append(badge);
        // imageNode.parentNode.append(badge);
        // document.body.appendChild(badge);
    }

    heading1(event) {
        
        // Set active button
        this.highlightButton(event);

        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('h1'));
        }   
        
    }

    heading2(event) {

        // Set active button
        this.highlightButton(event);

        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('h2'));
        }   
    }

    heading3(event)  {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('3'));
        } 
    }

    heading4(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('h4'));
        } 
    }

    heading5(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('h5'));
        } 
    }

    paragraph(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('p'));
        } 
    }

    italicize(event) {

        // Set active button
        this.highlightButton(event);

        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('i'));
        }    
    }

    bold(event) {

        // Set active button
        this.highlightButton(event);
        

        // Reject there's no selection or selection is not within editor div
        let selection   = document.getSelection();
        if(selection.rangeCount == 0) return;

        let targetNode  = selection.anchorNode;
        let parentNode  = selection.anchorNode.parentNode;

        if(!this.editor.contains(parentNode) || !this.editor.contains(selection.focusNode.parentNode)) return;

        let range   = selection.getRangeAt(0);

        // Expand selection to word if collapsed
        if(!selection.toString().trim().length) {
            
            range   = this.rangeToWord(range);
            if(!range) return;
        }

        // if(parentNode.closest('string', 'b')) {
        if(parentNode.nodeName == 'STRONG' || range.commonAncestorContainer.nodeName == 'STRONG') {

            let children    = Array.from(parentNode.childNodes);
            parentNode.replaceWith(...children);

            this.editor.normalize();
            return;
        }
                
        let element = document.createElement('strong');

        element.append(range.extractContents());
        range.insertNode(element);

        selection.removeAllRanges();

        let newRange = new Range();
        newRange.selectNodeContents(element);    
        selection.addRange(newRange);

        this.editor.normalize();            
    }

    underline(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('u'));
        } 
    }

    strikethrough(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('s'));
        } 
    }

    /**
     * Insert or remove hyperlinks from text.
     * 
     * @param {*} event 
     * @returns 
     */
    hyperlink(event) {

        // Set active button
        this.highlightButton(event);        
        let selection   = document.getSelection();

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        let range   = selection.getRangeAt(0);

        // Reject if selection outside content area
        if(!this.editor.contains(selection.anchorNode)) return;
        if(!this.editor.contains(selection.focusNode)) return;

        // Remove link if no selection
        if(!selection.toString().trim() || selection.isCollapsed) {

            let el = selection.anchorNode;
            
            if(el.parentNode.nodeName == 'A') {
                el.parentNode.replaceWith(...el.parentNode.childNodes);
            }
        }
        else {

            // Create an input field to get the URL from user
            let input   = document.createElement('input');
            input.type  = 'text';
            input.placeholder   = 'Paste your link here...';
            input.className = 'text-editor-hyperlink-input';

            // Handle submit event
            let handleInput = (event) => {

                if(event.key !== 'Enter') return;
                if(!event.target.value.trim()) return;

                let anchor     = document.createElement('a');
                anchor.href    = event.target.value.trim();
                anchor.setAttribute('target', '_blank');

                try { range.surroundContents(anchor); }
                catch { document.execCommand('createLink', false, event.target.value.trim()); }

                removeInput();               
            }

            // Remove anchor input when user clicks outside
            let removeInput = (event) => {

                input.removeEventListener('keydown', handleInput);
                document.removeEventListener('mousedown', removeInput);

                input.remove();
            }

            // Get coordinates
            let rect    = range.getBoundingClientRect();
            input.style.cssText   = `top:${rect.top - 30}px;left:${rect.left}px`;

            document.body.append(input);
            input.focus(); 

            input.onkeydown     =  handleInput;
            input.onmousedown   = (event) => { event.stopPropagation(); }

            // Delay listener to prevent the current click from self-closing popup
            setTimeout(() => { 
                document.addEventListener('mousedown', removeInput);
            }, 100);           
        }
    }

    ordered(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();


        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);
        // Reject if selection outside content area
        if(!this.editor.contains(selection.anchorNode)) return;
        if(!this.editor.contains(selection.focusNode)) return;

        let ol  = document.createElement('ol');
        let li  = document.createElement('li');

        li.appendChild(document.createElement('br'));

        // Empty ordered list
        if(selection.isCollapsed) {

            ol.appendChild(li);
            range.insertNode(ol);

            // range.anchorNode.parentNode.append(ol);

            range.setStart(li, 0);
            range.collapse(true);

            // Move cursor to start of element
            selection.removeAllRanges();
            selection.addRange(range);
        }
        // Ordered list with initial content
        else {

            let selectedText    = selection.toString().trim();
            li.appendChild(document.createTextNode(selectedText));

            li2     = document.createElement('li');
            li2.appendChild(document.createElement('br'));

            // Append created li items
            ol.append(li, li2);
            
            range.deleteContents();
            range.insertNode(ol);
            range.setStart(li2, 0);
            range.collapse(true);

            selection.removeAllRanges()
            selection.addRange(range);
        }
    }

    unordered(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('ul'));
        } 
    }

    quote(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('blockquote'));
        } 
    }

    code(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('pre'));
        } 
    }

    image(event) {

        // Set active button
        event.preventDefault();
        this.highlightButton(event);
        
        let selection   = document.getSelection();

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        let range   = selection.getRangeAt(0);

        // Reject if selection outside content area
        if(!this.editor.contains(selection.anchorNode)) return;
        if(!this.editor.contains(selection.focusNode)) return;   
        
        // Create an input field to get the image URL from user
        let input   = document.createElement('input');
        input.type  = 'text';
        input.placeholder   = 'Paste your image link here...';
        input.className = 'text-editor-hyperlink-input';

        // Handle submit event
        let handleInput = (event) => {

            if(event.key !== 'Enter') return;
            if(!event.target.value.trim()) return;

            let image    = document.createElement('img');
            image.src    = event.target.value.trim();

            // Delete text if there's a text selection
            if(!selection.isCollapsed) {

                range.deleteContents();
                selection.collapseToStart();
            }

            range.insertNode(image);
            removeInput();               
        }

        // Remove image input when user clicks outside
        let removeInput = (event) => {

            input.removeEventListener('keydown', handleInput);
            document.removeEventListener('mousedown', removeInput);

            input.remove();
        }

        // Get coordinates
        let rect    = range.getBoundingClientRect();

        if(rect.top === 0 && rect.top === 0) {
            rect    = selection.anchorNode?.getBoundingClientRect();
            //  || selection.anchorNode?.parentNode?.getBoundingClientRect();
        }
        input.style.cssText   = `top:${rect.top - 30}px;left:${rect.left}px`;

        document.body.append(input);
        input.focus(); 

        input.onkeydown     =  handleInput;
        input.onmousedown   = (event) => { event.stopPropagation(); }

        // Delay listener to prevent the current click from self-closing popup
        setTimeout(() => { 
            document.addEventListener('mousedown', removeInput);
        }, 100);       
    }

    video(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();
        let element     = selection.anchorNode.parentNode;

        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);

        if(!selection.toString().trim().length) return;

        if(this.editor.contains(element)) {    
            range.surroundContents(document.createElement('iframe'));
        } 
    }

    undo(event) {

        // Set active button
        this.highlightButton(event);

        // Give browser native command to undo
        document.execCommand('undo', false, null)
        
    }

    redo(event) {

        // Set active button
        this.highlightButton(event);

        // Give browser native command to undo
        document.execCommand('redo', false, null)    
    }

    left(event) {

        // Set active button
        this.highlightButton(event);
        
    }

    center(event) {

        // Set active button
        this.highlightButton(event);
        
    }

    right(event) {

        // Set active button
        this.highlightButton(event);
        
    }

    clear(event) {

        // Set active button
        this.highlightButton(event);
        
    }

    hr(event) {

        // Set active button
        this.highlightButton(event);
        
        let selection   = document.getSelection();


        // Reject there's not selection or selection is not in editor
        if(selection.rangeCount == 0) return;
        if(!this.editor.contains(element)) return;

        let range       = selection.getRangeAt(0);
        // Reject if selection outside content area
        if(!this.editor.contains(selection.anchorNode)) return;
        if(!this.editor.contains(selection.focusNode)) return;

        // Delete text if there's a selection
        if(!selection.isCollapsed) {

            range.deleteContents();
            selection.collapseToStart();
        }

        let hr     = document.createElement('hr');
        range.insertNode(hr);
    }

    html(event) {
        
        // Replace HTML icon with Rich Text icon
        event.target.closest('span').classList.add('hidden');
        event.target.closest('span').classList.remove('hover');

        let textIcon    = this.toolbar.querySelector('#text-editor-text');

        if(textIcon.classList.contains('hidden')) {

            textIcon.classList.remove('hidden');
            textIcon.classList.add('hover');
        }
        
        if(!this.editor.classList.contains('text-editor-html')) {
            this.editor.classList.add('text-editor-html');
        }

        this.editor.setAttribute('plaintext-only', true);
        this.editor.innerText = this.editor.innerHTML.replace(/<\/p>/g, '</p>\n');
    }

    text(event) {

        // Replace Rich Text icon with HTML icon
        event.target.closest('span').classList.add('hidden');
        event.target.closest('span').classList.remove('hover');
        
        let htmlIcon    = this.toolbar.querySelector('#text-editor-html');

        if(htmlIcon.classList.contains('hidden')) {

            htmlIcon.classList.remove('hidden');
            htmlIcon.classList.add('hover');
        }
        
        // Switch up Raw Html editor with Rich Text   
        if(this.editor.classList.contains('text-editor-html')) {
            this.editor.classList.remove('text-editor-html');
        }  

        this.editor.removeAttribute('plaintext-only');
        this.editor.innerHTML = this.editor.innerText.replace(/\n+/g, '');    
    }

    // Highlight Active Button
    highlightButton(event) {

        let button  = event.target.closest('span');

        this.toolbar.querySelectorAll(':scope > span').forEach((btn) => {
            btn.classList.remove('hover');
        });

        !button.classList.contains('hover') ? button.classList.add('hover') : '';
    }

    // Expand a collapse range to select the entire word
    rangeToWord(range) {

        let node    = range.startContainer;
        let start   = range.startOffset;
        let end     = range.endOffset;

        if(node.nodeType !== Node.TEXT_NODE) return;
        let text    = node.textContent;

        // Move start left until we hit a space or boundary
        while(start > 0 && /\S/.test(text[start - 1])) {
            start--;
        }

        // Move end right till me meet a space or boundary
        while(end < text.length && /\S/.test(text[end])) {
            end++;
        }

        range.setStart(node, start);
        range.setEnd(node, end);

        return range;
    }

    markup = `
    <div class="text-editor-toolbar">
        <span id="text-editor-heading1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h8"></path>
                <path d="M4 18V6"></path>
                <path d="M12 18V6"></path>
                <path d="M16 10l2-2v10"></path>
            </svg>
        </span>
        <div id="text-editor-headings-container" class="hidden" >                
            <span id="text-editor-heading2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h8"></path>
                <path d="M4 18V6"></path>
                <path d="M12 18V6"></path>
                <path d="M20 18h-4c0-1 2-2 2-3s-1-1.5-2-1"></path>
                </svg>
            </span>
            <span id="text-editor-heading3">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h8"></path>
                <path d="M4 18V6"></path>
                <path d="M12 18V6"></path>
                <path d="M16 8h4l-2 3.5a2.5 2.5 0 1 1-2 4"></path>
                </svg>
            </span>
            <span id="text-editor-heading4">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h8"></path>
                <path d="M4 18V6"></path>
                <path d="M12 18V6"></path>
                <path d="M17 14h3V6"></path>
                <path d="M17 6v8h4"></path>
                </svg>
            </span>
            <span id="text-editor-heading5">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12h8"></path>
                <path d="M4 18V6"></path>
                <path d="M12 18V6"></path>
                <path d="M20 8h-4v4.5c0 .5.5 1 1 1s2 .5 2 1.5-.5 1.5-1.5 1.5h-1.5"></path>
                </svg>
            </span>
        </div>
        <span id="text-editor-paragraph">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M13 4v16"></path>
            <path d="M17 4v16"></path>
            <path d="M19 4H9.5a4.5 4.5 0 0 0 0 9H13"></path>
            </svg>
        </span>
        <span id="text-editor-italicize">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="4" x2="10" y2="4"></line>
                <line x1="14" y1="20" x2="5" y2="20"></line>
                <line x1="15" y1="4" x2="9" y2="20"></line>
            </svg>
        </span>
        <span id="text-editor-bold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                <path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
            </svg>            </span>
        <span id="text-editor-underline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"></path>
                <line x1="4" y1="21" x2="20" y2="21"></line>
            </svg>
        </span>
        <span id="text-editor-strikethrough">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 4H9a4 4 0 0 0-4 4v1a4 4 0 0 0 4 4h6a4 4 0 0 1 4 4v1a4 4 0 0 1-4 4H7"></path>
                <line x1="4" y1="12" x2="20" y2="12"></line>
            </svg>
        </span>
        <span id="text-editor-hyperlink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
            </svg>
        </span>
        <span id="text-editor-unordered">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="9" y1="6" x2="20" y2="6"></line>
                <line x1="9" y1="12" x2="20" y2="12"></line>
                <line x1="9" y1="18" x2="20" y2="18"></line>
                <circle cx="4" cy="6" r="1"></circle>
                <circle cx="4" cy="12" r="1"></circle>
                <circle cx="4" cy="18" r="1"></circle>
            </svg>
        </span>
        <span id="text-editor-ordered">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="10" y1="6" x2="21" y2="6"></line>
                <line x1="10" y1="12" x2="21" y2="12"></line>
                <line x1="10" y1="18" x2="21" y2="18"></line>
                <path d="M4 6h1v4"></path>
                <path d="M4 10h2"></path>
                <path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path>
            </svg>
        </span>
        <span id="text-editor-quote">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 21c3 0 7-1 7-8V5c0-1.25-.75-2-2-2H4c-1.25 0-2 .75-2 2v4c0 1.25.75 2 2 2h3c0 4-2 6-5 6"></path>
                <path d="M15 21c3 0 7-1 7-8V5c0-1.25-.75-2-2-2h-4c-1.25 0-2 .75-2 2v4c0 1.25.75 2 2 2h3c0 4-2 6-5 6"></path>
            </svg>
        </span>
        <span id="text-editor-code">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="16 18 22 12 16 6"></polyline>
                <polyline points="8 6 2 12 8 18"></polyline>
            </svg>
        </span>
        <span id="text-editor-image">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
            </svg>
        </span>
        <span id="text-editor-video">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M23 7l-7 5 7 5V7z"></path>
                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
            </svg>
        </span>
        <span id="text-editor-undo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 7v6h6"></path>
                <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path>
            </svg>
        </span>
        <span id="text-editor-redo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 7v6h-6"></path>
                <path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"></path>
            </svg>
        </span>
        <span id="text-editor-left">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="17" y1="10" x2="3" y2="10"></line>
                <line x1="21" y1="6" x2="3" y2="6"></line>
                <line x1="21" y1="14" x2="3" y2="14"></line>
                <line x1="17" y1="18" x2="3" y2="18"></line>
            </svg>
        </span>
        <span id="text-editor-center">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="10" x2="6" y2="10"></line>
                <line x1="21" y1="6" x2="3" y2="6"></line>
                <line x1="21" y1="14" x2="3" y2="14"></line>
                <line x1="18" y1="18" x2="6" y2="18"></line>
            </svg>
        </span>
        <span id="text-editor-right">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="21" y1="10" x2="7" y2="10"></line>
                <line x1="21" y1="6" x2="3" y2="6"></line>
                <line x1="21" y1="14" x2="3" y2="14"></line>
                <line x1="21" y1="18" x2="7" y2="18"></line>
            </svg>
        </span>
        <span id="text-editor-clear">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 20V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v14"></path>
                <path d="M4 12h8"></path>
                <line x1="16" y1="14" x2="22" y2="20"></line>
                <line x1="22" y1="14" x2="16" y2="20"></line>
            </svg>
        </span>
        <span id="text-editor-hr">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="2" y1="12" x2="5" y2="12"></line>
                <line x1="9" y1="12" x2="15" y2="12"></line>
                <line x1="19" y1="12" x2="22" y2="12"></line>
            </svg>
        </span>
        <span id="text-editor-html">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="7 8 3 12 7 16"></polyline>
                <polyline points="17 8 21 12 17 16"></polyline>
                <line x1="14" y1="4" x2="10" y2="20"></line>
            </svg>
        </span>
        <span id="text-editor-text" class="hidden">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 20V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v14"></path>
                <path d="M4 12h8"></path>
                <line x1="18" y1="4" x2="18" y2="20"></line>
                <line x1="16" y1="4" x2="20" y2="4"></line>
                <line x1="16" y1="20" x2="20" y2="20"></line>
            </svg>
        </span>
    </div>`;

    styles = `
    .text-editor-div {
        width: 100%;
        min-height: 500px;
        position: relative;
        border: 2px solid skyblue;
        outline: none;
        padding: 10px; 
        line-height: 2rem;
        background-color: aliceblue;   
    }

    .text-editor-div.text-editor-html {
        font-family: monospace;
        background-color: rgba(211, 211, 211, 0.41);
        white-space: pre-wrap;
    }

    .text-editor-div:empty::before, 
    /* .text-editor-div:not(:has(text))::before, */
    .text-editor-div:has(> p:only-child > br:only-child)::before {
        content: attr(data-placeholder);
        position: absolute;
        top: 10px;
        left: 11px;
        color: lightgray;
        pointer-events: none;
    }

    .text-editor-toolbar {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(auto-fill, 30px);
        background-color: lightblue;
        font-size: .95rem;
    }

    .text-editor-toolbar span {
        width: 25px;
        height: 25px;
        margin: 1px;
        color: black;
        padding: 2px;
    }

    .text-editor-toolbar span:hover, .text-editor-toolbar .hover {
        cursor: pointer;
        background-color: rgb(162, 233, 255);
    }

    .text-editor-toolbar .hidden { display: none; }
    .text-editor-toolbar .display { display: block; }

    .text-editor-div blockquote {
        border-left: 4px solid deepskyblue;
        padding: 2px 4px;
        background-color: skyblue;
        font-style: italic;
    }

    .text-editor-div ul, .text-editor-div ol {
        list-style-position: inside;
    }

    .text-editor-div img {
        width: 100%;
    }

    .text-editor-hyperlink-input {
        outline: none;
        width: 200px;
        position: fixed;
        padding: 3px 5px;
        border-radius: 3px;
        border: 3px solid deepskyblue;
        background-color: aliceblue;
        z-index: 99;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .text-editor-upload-badge {
        position: absolute;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        z-index: 999;
        pointer-events: none;
    }
    .text-editor-upload-badge.success { background: #22c55e; color: white;}
    .text-editor-upload-badge.error { background: #ef4444; color: white;}
    `
}