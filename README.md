# Stock Trading Platform (Clearnet, Tor, and NoScript Fallback Compatible)
This is a feature-rich stock trading platform designed to work seamlessly across clearnet and Tor networks, with NoScript fallback compatibility.  
The platform demonstrates my skills in full-stack development, secure communication protocols, and cross-network interoperability.

## Table of Contents
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Challenges and Solutions](#challenges-and-solutions)
- [Acknowledgments](#acknowledgments)
- [License](#license)

---

## Features
- **Cross-Network Compatibility:** Fully functional on clearnet and Tor.
- **Fallback Mechanism:** Operates with most functionalities in NoScript mode.
- **Secure Transactions:** End-to-end encryption for sensitive data.
- **User-Friendly Interface:** Intuitive design for traders of all experience levels.
- **Mobile Compatibility:** Fully responsive design ensures seamless access across devices.
- **Enhanced Authentication:** Built-in multi-factor authentication (2FA) and reCAPTCHA for secure sign-ins.

---

## Technology Stack
- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **Security:** OpenSSL, Onion Routing
- **Hosting:** Deployed on AWS and accessible via Tor

---

## Challenges and Solutions

- **Challenge:** Securely integrating Tor compatibility without compromising clearnet functionality.
  - **Solution:** Developed checks to determine the type of network the website was being run on, enabling functionality on both clearnet and Tor without needing separate hosts.  
    By providing a single source, the platform works seamlessly for users on both networks without duplicating codebases.

- **Challenge:** Ensuring fallback compatibility for users without JavaScript.
  - **Solution:** Implemented fallback mechanisms that allow trading, viewing graphs, and loading wallets without requiring JavaScript.  
    For NoScript users, the backend dynamically generates graphs and displays them as static images, ensuring functionality.  
    Users with JavaScript enabled enjoy a streamlined, interactive experience.

---

## Acknowledgments
- [Tor Project](https://www.torproject.org/) for their open-source tools and documentation.

---

## License
This project is for demonstration purposes only and is not open source.
