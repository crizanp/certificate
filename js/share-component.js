/**
 * Certificate Share Component
 * Handles social media sharing functionality for certificates
 */

class CertificateShare {
    constructor() {
        this.currentCertificateCode = '';
        this.currentSyllabusName = '';
        this.currentVerificationUrl = '';
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('shareModal');
            if (e.target === modal) {
                this.closeShareModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeShareModal();
            }
        });
    }

    /**
     * Show share options modal
     * @param {string} certificateCode - The certificate code
     * @param {string} certificateImage - The certificate image path
     * @param {string} syllabusName - The syllabus/course name
     */
    showShareOptions(certificateCode, certificateImage, syllabusName) {
        this.currentCertificateCode = certificateCode;
        this.currentSyllabusName = syllabusName;
        this.currentVerificationUrl = this.generateVerificationUrl();
        
        const shareText = this.generateShareText();
        
        // Update social media links
        this.updateSocialLinks(shareText);
        
        // Show modal
        document.getElementById('shareModal').classList.add('show');
    }

    /**
     * Close share modal
     */
    closeShareModal() {
        document.getElementById('shareModal').classList.remove('show');
    }

    /**
     * Generate verification URL based on current search parameters
     */
    generateVerificationUrl() {
        const name = this.getCurrentName();
        const email = this.getCurrentEmail();
        return `${window.location.origin}${window.location.pathname}?name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}`;
    }

    /**
     * Generate share text message
     */
    generateShareText() {
        return `🎉 I have successfully completed the "${this.currentSyllabusName}" from Gyanhub! 🎓 I am proud to receive my certificate from Gyanhub. Here is the verification link for my certificate: ${this.currentVerificationUrl}`;
    }

    /**
     * Get current name from search form
     */
    getCurrentName() {
        const nameInput = document.getElementById('name');
        return nameInput ? nameInput.value : '';
    }

    /**
     * Get current email from search form
     */
    getCurrentEmail() {
        const emailInput = document.getElementById('email');
        return emailInput ? emailInput.value : '';
    }

    /**
     * Handle Facebook sharing with instruction
     * @param {Event} event - Click event
     */
    handleFacebookShare(event) {
        const shareText = this.generateShareText();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shareText).then(() => {
                alert('Message copied to clipboard! Facebook will open now - you can paste this message in your post.');
            }).catch(() => {
                alert('Please copy this message manually and paste it in Facebook:\n\n' + shareText);
            });
        } else {
            alert('Please copy this message manually and paste it in Facebook:\n\n' + shareText);
        }
        
        // Let the default action proceed (opening Facebook)
        return true;
    }

    /**
     * Update social media sharing links
     * @param {string} shareText - The text to share
     */
    updateSocialLinks(shareText) {
        const encodedText = encodeURIComponent(shareText);
        const encodedUrl = encodeURIComponent(this.currentVerificationUrl);
        const shortMessage = encodeURIComponent(`🎉 I completed "${this.currentSyllabusName}" from Gyanhub! 🎓`);
        
        // Facebook - Note: Facebook deprecated custom text in 2017 for security reasons
        const facebookShare = document.getElementById('facebookShare');
        if (facebookShare) {
            facebookShare.href = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
        }
        
        // LinkedIn - Works with title and summary
        const linkedinShare = document.getElementById('linkedinShare');
        if (linkedinShare) {
            linkedinShare.href = `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}&title=${shortMessage}&summary=${encodedText}`;
        }
        
        // Twitter - Works perfectly with text
        const twitterShare = document.getElementById('twitterShare');
        if (twitterShare) {
            twitterShare.href = `https://twitter.com/intent/tweet?text=${encodedText}`;
        }
        
        // WhatsApp - Works perfectly with text
        const whatsappShare = document.getElementById('whatsappShare');
        if (whatsappShare) {
            whatsappShare.href = `https://wa.me/?text=${encodedText}`;
        }
    }

    /**
     * Copy to clipboard function
     */
    copyToClipboard() {
        const shareText = this.generateShareText();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shareText).then(() => {
                this.showCopySuccess();
            }).catch(() => {
                this.fallbackCopyToClipboard(shareText);
            });
        } else {
            this.fallbackCopyToClipboard(shareText);
        }
    }

    /**
     * Fallback copy function for older browsers
     * @param {string} text - Text to copy
     */
    fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            this.showCopySuccess();
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
        
        document.body.removeChild(textArea);
    }

    /**
     * Show copy success message
     */
    showCopySuccess() {
        const successElement = document.getElementById('copySuccess');
        if (successElement) {
            successElement.classList.add('show');
            setTimeout(() => {
                successElement.classList.remove('show');
            }, 2000);
        }
    }
}

// Initialize the share component when DOM is loaded
let certificateShare;

document.addEventListener('DOMContentLoaded', () => {
    certificateShare = new CertificateShare();
});

// Global functions for backward compatibility
function showShareOptions(certificateCode, certificateImage, syllabusName) {
    if (certificateShare) {
        certificateShare.showShareOptions(certificateCode, certificateImage, syllabusName);
    }
}

function closeShareModal() {
    if (certificateShare) {
        certificateShare.closeShareModal();
    }
}

function handleFacebookShare(event) {
    if (certificateShare) {
        return certificateShare.handleFacebookShare(event);
    }
    return true;
}

function copyToClipboard() {
    if (certificateShare) {
        certificateShare.copyToClipboard();
    }
}
