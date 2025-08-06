<!-- Share Modal Component -->
<div id="shareModal" class="share-modal">
    <div class="share-content">
        <button class="share-close" onclick="closeShareModal()">&times;</button>
        <h3 class="share-title">Share My Certificate</h3>
        <div class="share-buttons">
            <a href="#" class="social-btn facebook" id="facebookShare" target="_blank" onclick="handleFacebookShare(event)">
                <i class="fab fa-facebook-f"></i>
                <span>Facebook</span>
            </a>
            <a href="#" class="social-btn linkedin" id="linkedinShare" target="_blank">
                <i class="fab fa-linkedin-in"></i>
                <span>LinkedIn</span>
            </a>
            <a href="#" class="social-btn twitter" id="twitterShare" target="_blank">
                <i class="fab fa-twitter"></i>
                <span>Twitter</span>
            </a>
            <a href="#" class="social-btn whatsapp" id="whatsappShare" target="_blank">
                <i class="fab fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
            <a href="#" class="social-btn copy" onclick="copyToClipboard()">
                <i class="fas fa-copy"></i>
                <span>Copy Link</span>
            </a>
        </div>
        <div class="copy-success" id="copySuccess">Link copied to clipboard!</div>
    </div>
</div>
