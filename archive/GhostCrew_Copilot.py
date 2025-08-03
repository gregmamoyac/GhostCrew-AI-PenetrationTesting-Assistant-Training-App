#!/usr/bin/env python3
"""
Penetration Testing AI Copilot Server
Educational tool for controlled penetration testing environment
"""

from flask import Flask, request, jsonify
import json
import re
from datetime import datetime

app = Flask(__name__)

class PentestCopilot:
    def __init__(self):
        self.command_patterns = {
            'ip_address': [
                r'find.*ip.*address',
                r'what.*my.*ip',
                r'show.*ip.*address',
                r'get.*ip.*address'
            ],
            'network_scan': [
                r'scan.*subnet',
                r'find.*hosts?.*online',
                r'discover.*hosts?',
                r'ping.*sweep',
                r'find.*ip.*addresses?.*online'
            ],
            'port_scan': [
                r'scan.*ports?',
                r'port.*scan',
                r'open.*ports?',
                r'running.*services?',
                r'service.*scan'
            ],
            'metasploit': [
                r'use.*metasploit',
                r'start.*metasploit',
                r'metasploit.*framework',
                r'^msfconsole$'
            ],
            'exploit_search': [
                r'find.*exploit',
                r'search.*exploit',
                r'exploit.*for',
                r'vulnerability.*search'
            ],
            'target_selection': [
                r'select.*target.*metasploit',
                r'select.*target.*exploit',
                r'set.*target.*metasploit',
                r'set.*target.*exploit',
                r'choose.*target.*metasploit',
                r'choose.*target.*exploit',
                r'target.*exploit.*metasploit',
                r'run.*exploit.*target',
                r'exploit.*target.*metasploit',
                r'metasploit.*target',
                r'metasploit.*select.*target',
                r'metasploit.*set.*target',
                r'metasploit.*exploit.*target'
            ],
            'password_collection': [
                r'collect.*password',
                r'get.*password.*file',
                r'download.*passwd',
                r'extract.*password'
            ],
            'password_cracking': [
                r'crack.*password',
                r'john.*ripper',
                r'password.*crack',
                r'unshadow'
            ]
        }

    def analyze_intent(self, user_input):
        """Analyze user input to determine intent with priority-based matching"""
        user_input_lower = user_input.lower()
        
        # Priority 1: Check for specific port scanning patterns first
        if any(word in user_input_lower for word in ['port', 'service']) and any(word in user_input_lower for word in ['scan', 'open']):
            return 'port_scan'
        
        # Priority 2: Check for network discovery/subnet scanning - more specific patterns
        if (('subnet' in user_input_lower and any(word in user_input_lower for word in ['find', 'scan', 'discover'])) or
            ('hosts' in user_input_lower and any(word in user_input_lower for word in ['online', 'find', 'discover'])) or
            re.search(r'\d+\.\d+\.\d+\.\d+/\d+', user_input_lower)):
            return 'network_scan'
        
        # Priority 3: Check for own IP address queries (must be very specific)
        if (any(phrase in user_input_lower for phrase in ['my ip', 'find my ip']) or
            (('find' in user_input_lower or 'what' in user_input_lower) and 'ip address' in user_input_lower and 
            not any(word in user_input_lower for word in ['scan', 'subnet', 'network', 'hosts', 'online']))):
            return 'ip_address'
        
        if any(word in user_input_lower for word in ['collect', 'download']) and 'password' in user_input_lower:
            return 'password_collection'

        # Priority 4: Check for target selection BEFORE general metasploit (MOVED UP)
        if 'metasploit' in user_input_lower and any(word in user_input_lower for word in ['target', 'select', 'set', 'choose', 'run']):
            return 'target_selection'
        
        # Also check for exploit + target combinations
        if 'exploit' in user_input_lower and any(word in user_input_lower for word in ['target', 'select', 'set', 'choose', 'run']):
            return 'target_selection'
        
        # Priority 5: Password-related activities
        if 'passwd' in user_input_lower and 'shadow' in user_input_lower:
            return 'password_cracking'
        if any(word in user_input_lower for word in ['crack', 'john', 'ripper']) and 'password' in user_input_lower:
            return 'password_cracking'
        
        # Priority 6: Exploit search (before general metasploit)
        if 'vsftpd' in user_input_lower or ('ftp' in user_input_lower and any(word in user_input_lower for word in ['exploit', 'vulnerability'])):
            return 'exploit_search'
        if 'exploit' in user_input_lower and any(word in user_input_lower for word in ['search', 'find', 'for']):
            return 'exploit_search'
        
        # Priority 7: General Metasploit (MOVED DOWN)
        if any(word in user_input_lower for word in ['metasploit', 'msfconsole']):
            # Check if it's NOT about target selection (already handled above)
            if not any(word in user_input_lower for word in ['target', 'select', 'set', 'choose', 'run', 'exploit']):
                return 'metasploit'
        
        # Priority 8: Fallback to pattern matching for remaining cases
        for intent, patterns in self.command_patterns.items():
            for pattern in patterns:
                if re.search(pattern, user_input_lower):
                    return intent
        
        return 'general'

    def generate_operator_response(self, user_input, chat_history=""):
        """Generate response for operator mode"""
        intent = self.analyze_intent(user_input)
        
        responses = {
            'ip_address': self._ip_address_response(),
            'network_scan': self._network_scan_response(user_input),
            'port_scan': self._port_scan_response(user_input),
            'metasploit': self._metasploit_response(),
            'exploit_search': self._exploit_search_response(user_input),
            'target_selection': self._target_selection_response(user_input),  # NEW
            'password_collection': self._password_collection_response(),
            'password_cracking': self._password_cracking_response(user_input),
            'general': self._general_response(user_input)
        }
        
        return responses.get(intent, self._general_response(user_input))

    def _ip_address_response(self):
        return """**Command:** `ip addr show`

**Explanation:** This command displays all network interfaces and their assigned IP addresses on your Kali Linux system. The `ip addr show` command is the modern replacement for the older `ifconfig` command and provides detailed network interface information.

**Reading Output:** Look for interface names like `eth0`, `wlan0`, or `tun0`. Under each interface, find the line starting with `inet` followed by an IP address (e.g., `inet 192.168.1.105/24`). The number after the slash indicates the subnet mask.

**Expected Actions:** 
- Note your primary interface IP address for network reconnaissance
- Identify if you're on the expected network range
- Check for VPN interfaces (tun0, tap0) if using tunneling

**Risks:** This is a reconnaissance command with minimal risk. However, be aware that network interface enumeration could potentially reveal information about your attack platform's network configuration to monitoring systems."""

    def _network_scan_response(self, user_input):
        # Extract subnet if mentioned, otherwise use common example
        subnet_match = re.search(r'(\d+\.\d+\.\d+\.\d+/\d+)', user_input)
        if subnet_match:
            subnet = subnet_match.group(1)
        else:
            # Look for common subnet patterns in the question
            if '192.168.1' in user_input:
                subnet = "192.168.1.0/24"
            else:
                subnet = "192.168.1.0/24"  # Default example
        
        return f"""**Command:** `nmap -sP {subnet}`

**Explanation:** This performs a ping sweep (host discovery) across the specified subnet to identify live hosts. The `-sP` flag tells Nmap to skip port scanning and only perform host discovery using ping probes, ARP requests, and other discovery methods.

**Reading Output:** Look for lines containing "Host is up" followed by response times. Each discovered host will show its IP address and potentially hostname. MAC addresses and vendor information may also be displayed for local network hosts.

**Expected Actions:**
- Document all discovered IP addresses for further analysis
- Note any interesting hostnames that might indicate servers or specific services
- Use discovered IPs as targets for more detailed port scanning with `nmap -sS -sV -O [target_ip]`

**Risks:** 
- Network administrators may detect ping sweeps in logs
- Some firewalls or IDS systems trigger alerts on systematic network scanning
- Aggressive scanning might cause network congestion
- Be mindful of scanning production networks outside your authorized test environment"""

    def _port_scan_response(self, user_input):
        # Extract IP if mentioned, otherwise use placeholder
        ip_match = re.search(r'(\d+\.\d+\.\d+\.\d+)(?!/)', user_input)  # IP without subnet mask
        target_ip = ip_match.group(1) if ip_match else "TARGET_IP"
        
        return f"""**Command:** `nmap -sS -sV -O {target_ip}`

**Explanation:** This performs a comprehensive scan combining SYN stealth scanning (-sS), service version detection (-sV), and OS fingerprinting (-O). The SYN scan is stealthier than full TCP connects, version detection identifies specific service versions, and OS detection attempts to determine the target's operating system.

**Reading Output:** The output shows:
- Open ports with their protocol (tcp/udp) and port numbers
- Service names and detected versions (crucial for vulnerability research)
- OS detection results with confidence percentages
- MAC address and network distance information

**Expected Actions:**
- Focus on services with known vulnerabilities (check versions against CVE databases)
- Research default credentials for identified services
- Plan exploitation strategy based on discovered attack surface
- Document all findings for reporting

**Risks:**
- SYN scans can still be detected by modern IDS/IPS systems
- Version detection creates more network traffic and logs
- OS fingerprinting generates distinctive probe patterns
- Scanning may trigger automated defense responses
- Some services may crash or become unstable when probed"""

    def _metasploit_response(self):
        return """**Command:** `msfconsole`

**Explanation:** This launches the Metasploit Framework console, a powerful penetration testing platform containing hundreds of exploits, payloads, encoders, and auxiliary modules. Metasploit provides a structured approach to exploitation with extensive automation capabilities.

**Reading Output:** You'll see the Metasploit banner with version information and module statistics. The prompt changes to `msf6 >` indicating you're in the Metasploit console. Loading may take 30-60 seconds depending on your system.

**Expected Actions:**
- Use `search` commands to find relevant exploits for discovered services
- Use `info` to get detailed information about specific modules
- Set required options like RHOSTS (target) and LHOST (your IP)
- Use `show options` to verify configuration before running exploits

**Risks:**
- Metasploit modules can cause service crashes or system instability
- Exploitation attempts are easily logged and detected
- Some payloads may trigger antivirus or EDR solutions
- Failed exploitation attempts might lock accounts or trigger security responses
- Always ensure you have proper authorization before using exploitation tools"""

    def _exploit_search_response(self, user_input):
        # Extract service/version if mentioned
        service_match = re.search(r'(vsftpd|ftp|ssh|apache|mysql|[\w\s]+\s+\d+\.\d+\.\d+)', user_input.lower())
        service = service_match.group(1) if service_match else "SERVICE_NAME"

        return f"""**Step 1: Search for Exploits**

**Command:** `search {service}`

**Explanation:** This searches the Metasploit database for modules related to the specified service or vulnerability. The search function looks through exploit names, descriptions, and references to find relevant modules.

**Reading Output:** Results show:
- Module names with their full paths (exploit/unix/ftp/vsftpd_234_backdoor)
- Disclosure dates indicating when vulnerabilities were discovered
- Rank (excellent, great, good) indicating reliability and impact
- Check column showing if the module can verify vulnerability without exploiting

**Step 2: Select an Exploit**

**Command:** `use exploit/unix/ftp/vsftpd_234_backdoor`

**Explanation:** The `use` command loads the specified exploit module into Metasploit's context. Replace the module path with the one you want from your search results. Once loaded, you can configure the exploit parameters and run it against your target.

**Step 3: Check Exploit Options**

**Command:** `show options`

**Explanation:** This displays all configurable options for the currently selected exploit module. You'll see required and optional parameters, their current values, and descriptions of what each option does.

**Expected Actions:**
- Select modules with "excellent" or "great" rankings for higher success rates
- Use `info MODULE_NAME` to get detailed information about the exploit before using it
- Set required options like RHOSTS (target IP) using `set RHOSTS [target_ip]`
- Verify the module works against your specific target configuration before running

**Risks:**
- Using wrong exploits can crash target services
- Some exploits may cause permanent damage or data loss
- Failed exploitation attempts are heavily logged
- Multiple exploit attempts may trigger lockout mechanisms
- Always verify you're targeting the correct service version"""



    def _password_collection_response(self):
        return """**Step 1: Download Password File**

**Command:** `download /etc/passwd /home/kali/Desktop/files/passwd`

**Explanation:** This command extracts the `/etc/passwd` file from the compromised Linux system to your local Kali machine. The passwd file contains user account information including usernames, user IDs, group IDs, home directories, and default shells. While it doesn't contain actual passwords, it's essential for understanding the user structure.

**Reading Output:** 
- Success message: "Download /etc/passwd => /home/kali/Desktop/files/passwd [+] Done"
- File size information indicating successful transfer
- Any error messages about permissions or missing files

**Step 2: Download Shadow File**

**Command:** `download /etc/shadow /home/kali/Desktop/files/shadow`

**Explanation:** This command extracts the `/etc/shadow` file which contains the actual password hashes for user accounts. The shadow file is readable only by root, so you need elevated privileges to access it. This file contains the hashed passwords, password aging information, and account expiration data.

**Reading Output:**
- Success message: "Download /etc/shadow => /home/kali/Desktop/files/shadow [+] Done"
- File size confirmation showing the transfer completed
- Permission errors if you don't have sufficient privileges

**Expected Actions:**
- Verify both files downloaded successfully to your local system
- Check file contents using `cat` or `head` to ensure they're not empty or corrupted
- Prepare files for password cracking using tools like John the Ripper
- Document user accounts found for further privilege escalation opportunities

**Risks:**
- Downloading system files creates obvious evidence of compromise in system logs
- File access generates audit logs on most properly configured systems
- Large file transfers may be detected by network monitoring tools
- Storing password hashes may violate data protection regulations in some jurisdictions
- These actions typically constitute unauthorized access under computer crime laws
- Always ensure you have explicit written authorization for these activities in your test environment"""

    def _target_selection_response(self, user_input):
        return f"""**Step 1: Select Your Exploit**

**Command:** `use exploit/[cat]/[svc]/[name]`

**Explanation:** First, choose and load the exploit module you want to use. Replace the bracketed sections with the actual exploit path from your search results.

**Step 2: Check Required Options**

**Command:** `show options`

**Explanation:** This displays all configurable parameters for the exploit. Look for options marked as "Required" - these must be set before you can run the exploit. Common required options include RHOSTS (target IP address) and RPORT (target port).

**Reading Output:** You'll see a table showing:
- Option names (RHOSTS, RPORT, LHOST, etc.)
- Current settings (often blank for required options)
- Required status (yes/no)
- Description of what each option does

**Step 3: Set Target Information**

**Command:** `set RHOSTS [target_ip_address]`

**Explanation:** Set the RHOSTS option to your target's IP address. This tells Metasploit which host to attack. You may also need to set other options like RPORT (target port) depending on the exploit.

**Step 4: Run the Exploit**

**Command:** `exploit`

**Explanation:** This launches the exploit against your configured target. If successful, you should get a shell session on the target system. The exploit command will show progress and any error messages.

**Expected Actions:**
- Always run `show options` before exploiting to verify your settings
- Set LHOST to your attacking machine's IP if required
- Use `check` command if available to verify target vulnerability without exploiting
- Document successful exploitation for your penetration test report

**Risks:**
- Exploits can crash target services or systems
- Failed attempts create logs that may alert administrators
- Some exploits require specific timing or conditions to work
- Always ensure you have proper authorization before exploiting any target
- Test exploits in lab environments before using on actual targets"""

    def _password_cracking_response(self, user_input):
        passwd_path = "/home/kali/Desktop/files/passwd"
        shadow_path = "/home/kali/Desktop/files/shadow"
        
        return f"""**Step 0: Exit Interactive Sessions**

**Command:** `exit`

**Explanation:** Before starting password cracking, make sure you're not in any interactive sessions (like Metasploit, SSH, or other tools). Use the `exit` command or `end` command to return to your normal terminal prompt. This ensures the cracking tools will work properly.

**Step 1: Combine Password Files**

**Command:** `unshadow {passwd_path} {shadow_path} > /home/kali/Desktop/files/pwds`

**Explanation:** The `unshadow` command combines the passwd and shadow files into a single file format that John the Ripper can process. The passwd file contains user account information while shadow contains the password hashes. This tool merges them into username:hash pairs.

**Reading Output:** This command typically produces no visible output if successful. Check that the output file `/home/kali/Desktop/files/pwds` was created and contains data using `ls -la /home/kali/Desktop/files/`.

**Step 2: Crack the Passwords**

**Command:** `john /home/kali/Desktop/files/pwds`

**Explanation:** This launches John the Ripper against the combined password file. John will automatically detect the hash format and begin cracking using its default wordlist and rules. It will try dictionary attacks first, then move to more advanced techniques.

**Reading Output:** John displays:
- Progress information showing passwords/second rate
- Currently attempted password patterns
- Any successfully cracked passwords in real-time
- Session information for resuming later

**Step 3: View Cracked Passwords**

**Command:** `john --show /home/kali/Desktop/files/pwds`

**Explanation:** This displays all previously cracked passwords from the specified file. John maintains a record of successful cracks, so this command shows the final results without re-running the cracking process.

**Reading Output:** Successfully cracked passwords appear as `username:plaintext_password` pairs. If no passwords were cracked, you'll see a message indicating zero passwords cracked.

**Expected Actions:**
- Always exit interactive sessions before running system tools
- Start with default wordlists, then try custom dictionaries if needed
- Use `--wordlist=` option to specify custom password lists for better results
- Try different cracking modes (dictionary, brute-force, hybrid) if initial attempts fail
- Document all cracked credentials for further system access attempts

**Risks:**
- Password cracking is CPU-intensive and may cause system performance issues
- Extended cracking sessions generate significant heat and power consumption
- Storing cracked passwords creates sensitive data that must be protected
- Using cracked passwords for unauthorized access is illegal without proper authorization
- Some organizations monitor for password cracking tool signatures
- Always ensure you have explicit permission to crack passwords in your test environment"""

    def _general_response(self, user_input):
        return f"""I understand you're asking about: "{user_input}"

For penetration testing guidance, please specify what you'd like to accomplish:
- Network reconnaissance and host discovery
- Port scanning and service enumeration  
- Vulnerability research and exploitation
- Password attacks and credential harvesting
- Post-exploitation activities

**General Security Reminder:** All penetration testing activities should only be performed in authorized test environments. Ensure you have proper written authorization before conducting any security testing activities.

Please provide more specific details about your testing objectives so I can give you targeted command recommendations with proper explanations and risk assessments."""

    def generate_admin_summary(self, session_id, command_history):
        """Generate summary for admin mode"""
        try:
            commands = json.loads(command_history)
            summary = self._analyze_session(session_id, commands)
            return summary
        except json.JSONDecodeError:
            return "Error: Invalid command history format"

    def _analyze_session(self, session_id, commands):
        """Analyze the penetration testing session"""
        if not commands:
            return "No commands executed in this session."

        # Extract key information
        start_time = commands[0]['timestamp'] if commands else "Unknown"
        end_time = commands[-1]['timestamp'] if commands else "Unknown"
        total_commands = len(commands)
        
        # Categorize activities
        reconnaissance = []
        exploitation = []
        post_exploitation = []
        errors = []
        
        for cmd in commands:
            command = cmd['command'].lower()
            status = cmd.get('status', 'unknown')
            
            if status != 'completed':
                errors.append(cmd)
                continue
                
            if any(tool in command for tool in ['ifconfig', 'ip addr', 'nmap']):
                reconnaissance.append(cmd)
            elif any(tool in command for tool in ['msfconsole', 'metasploit', 'exploit']):
                exploitation.append(cmd)
            elif any(activity in command for activity in ['download', 'john', 'unshadow']):
                post_exploitation.append(cmd)

        # Generate summary
        summary = f"""
**PENETRATION TESTING SESSION SUMMARY**
Session ID: {session_id}
Duration: {start_time} to {end_time}
Total Commands: {total_commands}

**RECONNAISSANCE PHASE ({len(reconnaissance)} commands):**
"""
        
        if reconnaissance:
            for cmd in reconnaissance:
                if 'ifconfig' in cmd['command'] or 'ip addr' in cmd['command']:
                    summary += "- ✓ Network interface enumeration completed\n"
                elif 'nmap -sP' in cmd['command'] or 'nmap -sn' in cmd['command']:
                    # Count discovered hosts
                    host_count = cmd['response'].count('Host is up')
                    summary += f"- ✓ Network discovery: {host_count} live hosts identified\n"
                elif 'nmap -sS' in cmd['command']:
                    # Count open ports
                    port_count = cmd['response'].count('/tcp   open')
                    summary += f"- ✓ Port scan completed: {port_count} open ports discovered\n"
        else:
            summary += "- No reconnaissance activities detected\n"

        summary += f"\n**EXPLOITATION PHASE ({len(exploitation)} commands):**\n"
        
        if exploitation:
            for cmd in exploitation:
                if 'msfconsole' in cmd['command']:
                    summary += "- ✓ Metasploit Framework initialized\n"
                elif 'search' in cmd['command'] and 'vsftpd' in cmd['command']:
                    summary += "- ✓ Vulnerability research: VSFTPD 2.3.4 backdoor identified\n"
                elif 'exploit' in cmd['command'] or 'use exploit' in cmd['command']:
                    if 'session 1 opened' in cmd['response']:
                        summary += "- ✓ Successful exploitation: Root shell obtained on target\n"
                    else:
                        summary += "- ⚠ Exploitation attempted but results unclear\n"
        else:
            summary += "- No exploitation activities detected\n"

        summary += f"\n**POST-EXPLOITATION PHASE ({len(post_exploitation)} commands):**\n"
        
        if post_exploitation:
            passwd_downloaded = False
            shadow_downloaded = False
            passwords_cracked = False
            
            for cmd in post_exploitation:
                if 'download /etc/passwd' in cmd['command']:
                    if 'Done' in cmd['response']:
                        passwd_downloaded = True
                        summary += "- ✓ Password file (passwd) successfully extracted\n"
                elif 'download /etc/shadow' in cmd['command']:
                    if 'Done' in cmd['response']:
                        shadow_downloaded = True
                        summary += "- ✓ Shadow file successfully extracted\n"
                elif 'john' in cmd['command']:
                    if 'No password hashes loaded' in cmd['response']:
                        summary += "- ⚠ Password cracking failed: File format issues\n"
                    else:
                        summary += "- ✓ Password cracking attempted\n"
                elif 'unshadow' in cmd['command']:
                    summary += "- ✓ Password files combined for cracking\n"
        else:
            summary += "- No post-exploitation activities detected\n"

        # Error analysis
        if errors:
            summary += f"\n**ERRORS AND ISSUES ({len(errors)} occurrences):**\n"
            for error in errors:
                summary += f"- Command '{error['command']}' failed or incomplete\n"

        # Overall assessment
        summary += "\n**OVERALL ASSESSMENT:**\n"
        
        if reconnaissance and exploitation:
            success_indicators = []
            if any('session 1 opened' in cmd.get('response', '') for cmd in commands):
                success_indicators.append("successful system compromise")
            if any('Done' in cmd.get('response', '') and 'download' in cmd['command'] for cmd in commands):
                success_indicators.append("data extraction")
            
            if success_indicators:
                summary += f"✓ **Successful penetration test:** Achieved {', '.join(success_indicators)}\n"
            else:
                summary += "⚠ **Partial success:** Reconnaissance completed but exploitation results unclear\n"
        elif reconnaissance:
            summary += "⚠ **Reconnaissance only:** Target information gathered but no exploitation attempted\n"
        else:
            summary += "⚠ **Limited activity:** Minimal penetration testing activities detected\n"

        summary += "\n**RECOMMENDATIONS:**\n"
        if not exploitation:
            summary += "- Consider attempting exploitation of identified vulnerabilities\n"
        if post_exploitation and any('john' in cmd['command'] and 'No password hashes loaded' in cmd.get('response', '') for cmd in commands):
            summary += "- Review password file formats and cracking procedures\n"
        if errors:
            summary += "- Address failed commands and technical issues\n"
        
        summary += "- Document all findings for final penetration test report\n"
        summary += "- Ensure proper cleanup and remediation recommendations\n"

        return summary.strip()

# Flask server setup
copilot = PentestCopilot()

@app.route('/', methods=['POST'])
def handle_request():
    """
    Main endpoint to handle requests from PHP script
    Returns JSON response compatible with the PHP error handling logic
    """
    try:
        # Log the incoming request for debugging
        app.logger.info(f"Received request from {request.remote_addr}")
        app.logger.info(f"User-Agent: {request.headers.get('User-Agent', 'Unknown')}")
        
        data = request.get_json()
        
        if not data:
            app.logger.error("No JSON data received")
            return jsonify({
                'error': 'No JSON data received',
                'status': 'error'
            }), 400
        
        app.logger.info(f"Request data: {data}")
        
        if 'mode' not in data:
            app.logger.error("Missing 'mode' field in request")
            return jsonify({
                'error': 'Missing required field: mode',
                'status': 'error'
            }), 400
        
        mode = data['mode']
        
        if mode == 'operator':
            user_input = data.get('input', '')
            chat_history = data.get('chat_history', '')
            
            if not user_input.strip():
                return jsonify({
                    'generated_text': 'Please provide a question or command request.',
                    'status': 'success'
                }), 200
            
            response_text = copilot.generate_operator_response(user_input, chat_history)
            
            return jsonify({
                'generated_text': response_text,
                'status': 'success'
            }), 200
            
        elif mode == 'admin':
            session_id = data.get('session_id', '')
            command_history = data.get('input', '')
            
            response_text = copilot.generate_admin_summary(session_id, command_history)
            
            return response_text

            # return jsonify({
            #     'generated_text': response_text,
            #     'status': 'success'
            # }), 200
            
        else:
            app.logger.error(f"Invalid mode: {mode}")
            return jsonify({
                'error': f'Invalid mode: {mode}. Use "operator" or "admin"',
                'status': 'error'
            }), 400
            
    except json.JSONDecodeError as e:
        app.logger.error(f"JSON decode error: {str(e)}")
        return jsonify({
            'error': 'Invalid JSON format',
            'status': 'error'
        }), 400
        
    except Exception as e:
        app.logger.error(f"Server error: {str(e)}")
        return jsonify({
            'error': f'Server error: {str(e)}',
            'status': 'error'
        }), 500

@app.route('/api/copilot', methods=['POST'])
def handle_legacy_request():
    """
    Legacy endpoint for backward compatibility
    Redirects to main endpoint
    """
    return handle_request()

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'status': 'healthy', 
        'service': 'pentest-copilot',
        'version': '1.0',
        'endpoints': {
            'POST /': 'Main copilot interface',
            'POST /api/copilot': 'Legacy endpoint (redirects to main)',
            'GET /health': 'Health check'
        }
    })

@app.errorhandler(404)
def not_found(error):
    return jsonify({
        'error': 'Endpoint not found. Use POST / for main interface.',
        'status': 'error'
    }), 404

@app.errorhandler(405)
def method_not_allowed(error):
    return jsonify({
        'error': 'Method not allowed. Use POST for main interface.',
        'status': 'error'
    }), 405

@app.before_request
def log_request_info():
    """Log incoming requests for debugging"""
    if request.endpoint != 'health_check':  # Don't log health checks
        app.logger.info(f"Request: {request.method} {request.path} from {request.remote_addr}")

if __name__ == '__main__':
    import logging
    
    # Configure logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    print("Starting Penetration Testing AI Copilot Server...")
    print("Server will run on http://192.168.1.171:8090")
    print("Configured for PHP integration with User-Agent: GhostCrew-Terminal/1.0")
    print("\nEndpoints:")
    print("  POST / - Main copilot interface (primary)")
    print("  POST /api/copilot - Legacy endpoint")
    print("  GET /health - Health check")
    print("\nExpected JSON format:")
    print('  Operator: {"mode":"operator","input":"your question","chat_history":""}')
    print('  Admin: {"mode":"admin","session_id":"sess_123","input":"[command_history]"}')
    print("\nResponse format:")
    print('  Success: {"generated_text":"response","status":"success"}')
    print('  Error: {"error":"error message","status":"error"}')
    
    app.run(host='192.168.1.171', port=8090, debug=True)
