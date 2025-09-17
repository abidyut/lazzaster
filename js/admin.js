// Admin Panel JavaScript
class AdminManager {
    constructor() {
        this.API_BASE_URL = window.location.origin;
        this.currentTab = 'users';
        this.adminToken = localStorage.getItem('adminToken');
        this.currentData = {
            users: [],
            deposits: [],
            withdrawals: [],
            settings: [],
            logs: []
        };
        this.init();
    }

    init() {
        // Check if admin is already logged in
        if (this.adminToken) {
            this.showDashboard();
            this.loadUsers();
        }

        // Setup login form
        document.getElementById('adminLoginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });
    }

    async login() {
        const username = document.getElementById('adminUsername').value;
        const password = document.getElementById('adminPassword').value;
        const messageDiv = document.getElementById('loginMessage');

        try {
            const response = await fetch(`${this.API_BASE_URL}/api/admin.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'login',
                    username: username,
                    password: password
                })
            });

            const result = await response.json();
            if (result.success) {
                this.adminToken = result.token;
                localStorage.setItem('adminToken', result.token);
                this.showDashboard();
                this.loadUsers();
                messageDiv.textContent = '';
            } else {
                messageDiv.textContent = result.message || 'Login failed';
                messageDiv.className = 'message error';
            }
        } catch (error) {
            console.error('Login error:', error);
            messageDiv.textContent = 'Network error occurred';
            messageDiv.className = 'message error';
        }
    }

    logout() {
        localStorage.removeItem('adminToken');
        this.adminToken = null;
        document.getElementById('loginSection').style.display = 'block';
        document.getElementById('adminDashboard').style.display = 'none';
        document.getElementById('adminUsername').value = '';
        document.getElementById('adminPassword').value = '';
    }

    showDashboard() {
        document.getElementById('loginSection').style.display = 'none';
        document.getElementById('adminDashboard').style.display = 'block';
    }

    showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active from all tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName + 'Tab').classList.add('active');
        event.target.classList.add('active');
        
        this.currentTab = tabName;
        
        // Load data for the selected tab
        switch(tabName) {
            case 'users':
                this.loadUsers();
                break;
            case 'deposits':
                this.loadDeposits();
                break;
            case 'withdrawals':
                this.loadWithdrawals();
                break;
            case 'balances':
                this.loadBalanceHistory();
                break;
            case 'settings':
                this.loadSettings();
                break;
            case 'logs':
                this.loadLogs();
                break;
        }
    }

    async apiCall(data) {
        try {
            const response = await fetch(`${this.API_BASE_URL}/api/admin.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.adminToken
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (!result.success && result.message === 'Invalid token') {
                this.logout();
                return null;
            }
            return result;
        } catch (error) {
            console.error('API call error:', error);
            return { success: false, message: 'Network error' };
        }
    }

    // User Management
    async loadUsers() {
        const usersList = document.getElementById('usersList');
        usersList.innerHTML = '<div class="loading">Loading users...</div>';

        const result = await this.apiCall({ action: 'get_users' });
        if (result && result.success) {
            this.currentData.users = result.data;
            this.renderUsers(result.data);
        } else {
            usersList.innerHTML = '<div class="message error">Failed to load users</div>';
        }
    }

    renderUsers(users) {
        const usersList = document.getElementById('usersList');
        if (!users || users.length === 0) {
            usersList.innerHTML = '<div class="message">No users found</div>';
            return;
        }

        const html = users.map(user => `
            <div class="list-item">
                <div class="item-info">
                    <div class="item-title">${user.username}</div>
                    <div class="item-subtitle">${user.email} | Balance: ${user.balance} ZST</div>
                    <div class="item-subtitle">Joined: ${new Date(user.created_at).toLocaleDateString()}</div>
                </div>
                <div class="status-badge status-${user.status}">${user.status}</div>
                <div class="item-actions">
                    <button class="btn-sm btn-primary" onclick="adminManager.editUser(${user.id})">Edit</button>
                    ${user.status === 'active' ? 
                        `<button class="btn-sm btn-warning" onclick="adminManager.suspendUser(${user.id})">Suspend</button>` :
                        `<button class="btn-sm btn-success" onclick="adminManager.activateUser(${user.id})">Activate</button>`
                    }
                </div>
            </div>
        `).join('');
        
        usersList.innerHTML = html;
    }

    filterUsers() {
        const searchTerm = document.getElementById('userSearch').value.toLowerCase();
        const filteredUsers = this.currentData.users.filter(user => 
            user.username.toLowerCase().includes(searchTerm) || 
            user.email.toLowerCase().includes(searchTerm)
        );
        this.renderUsers(filteredUsers);
    }

    async suspendUser(userId) {
        if (!confirm('Are you sure you want to suspend this user?')) return;
        
        const result = await this.apiCall({
            action: 'update_user_status',
            user_id: userId,
            status: 'suspended'
        });
        
        if (result && result.success) {
            this.loadUsers();
            this.showMessage('User suspended successfully', 'success');
        } else {
            this.showMessage(result?.message || 'Failed to suspend user', 'error');
        }
    }

    async activateUser(userId) {
        const result = await this.apiCall({
            action: 'update_user_status',
            user_id: userId,
            status: 'active'
        });
        
        if (result && result.success) {
            this.loadUsers();
            this.showMessage('User activated successfully', 'success');
        } else {
            this.showMessage(result?.message || 'Failed to activate user', 'error');
        }
    }

    // Deposit Management
    async loadDeposits() {
        const depositsList = document.getElementById('depositsList');
        depositsList.innerHTML = '<div class="loading">Loading deposits...</div>';

        const result = await this.apiCall({ action: 'get_deposits' });
        if (result && result.success) {
            this.currentData.deposits = result.data;
            this.renderDeposits(result.data);
        } else {
            depositsList.innerHTML = '<div class="message error">Failed to load deposits</div>';
        }
    }

    renderDeposits(deposits) {
        const depositsList = document.getElementById('depositsList');
        if (!deposits || deposits.length === 0) {
            depositsList.innerHTML = '<div class="message">No deposits found</div>';
            return;
        }

        const html = deposits.map(deposit => `
            <div class="list-item">
                <div class="item-info">
                    <div class="item-title">${deposit.username} - ${deposit.zst_amount} ZST</div>
                    <div class="item-subtitle">Method: ${deposit.method} | TxID: ${deposit.transaction_id}</div>
                    <div class="item-subtitle">Date: ${new Date(deposit.created_at).toLocaleString()}</div>
                    ${deposit.admin_notes ? `<div class="item-subtitle">Notes: ${deposit.admin_notes}</div>` : ''}
                </div>
                <div class="status-badge status-${deposit.status}">${deposit.status}</div>
                <div class="item-actions">
                    ${deposit.status === 'pending' ? `
                        <button class="btn-sm btn-success" onclick="adminManager.approveDeposit(${deposit.id})">Approve</button>
                        <button class="btn-sm btn-danger" onclick="adminManager.rejectDeposit(${deposit.id})">Reject</button>
                    ` : ''}
                    <button class="btn-sm btn-primary" onclick="adminManager.editDeposit(${deposit.id})">Details</button>
                </div>
            </div>
        `).join('');
        
        depositsList.innerHTML = html;
    }

    filterDeposits() {
        const status = document.getElementById('depositStatusFilter').value;
        const filteredDeposits = status ? 
            this.currentData.deposits.filter(deposit => deposit.status === status) :
            this.currentData.deposits;
        this.renderDeposits(filteredDeposits);
    }

    async approveDeposit(depositId) {
        if (!confirm('Are you sure you want to approve this deposit?')) return;
        
        const result = await this.apiCall({
            action: 'update_deposit_status',
            deposit_id: depositId,
            status: 'approved'
        });
        
        if (result && result.success) {
            this.loadDeposits();
            this.showMessage('Deposit approved successfully', 'success');
        } else {
            this.showMessage(result?.message || 'Failed to approve deposit', 'error');
        }
    }

    async rejectDeposit(depositId) {
        const reason = prompt('Please provide a reason for rejection:');
        if (!reason) return;
        
        const result = await this.apiCall({
            action: 'update_deposit_status',
            deposit_id: depositId,
            status: 'rejected',
            admin_notes: reason
        });
        
        if (result && result.success) {
            this.loadDeposits();
            this.showMessage('Deposit rejected successfully', 'success');
        } else {
            this.showMessage(result?.message || 'Failed to reject deposit', 'error');
        }
    }

    // Withdrawal Management
    async loadWithdrawals() {
        const withdrawalsList = document.getElementById('withdrawalsList');
        withdrawalsList.innerHTML = '<div class="loading">Loading withdrawals...</div>';

        const result = await this.apiCall({ action: 'get_withdrawals' });
        if (result && result.success) {
            this.currentData.withdrawals = result.data;
            this.renderWithdrawals(result.data);
        } else {
            withdrawalsList.innerHTML = '<div class="message error">Failed to load withdrawals</div>';
        }
    }

    renderWithdrawals(withdrawals) {
        const withdrawalsList = document.getElementById('withdrawalsList');
        if (!withdrawals || withdrawals.length === 0) {
            withdrawalsList.innerHTML = '<div class="message">No withdrawals found</div>';
            return;
        }

        const html = withdrawals.map(withdrawal => `
            <div class="list-item">
                <div class="item-info">
                    <div class="item-title">${withdrawal.username} - ${withdrawal.zst_amount} ZST</div>
                    <div class="item-subtitle">Method: ${withdrawal.method}</div>
                    <div class="item-subtitle">Date: ${new Date(withdrawal.created_at).toLocaleString()}</div>
                    ${withdrawal.admin_notes ? `<div class="item-subtitle">Notes: ${withdrawal.admin_notes}</div>` : ''}
                </div>
                <div class="status-badge status-${withdrawal.status}">${withdrawal.status}</div>
                <div class="item-actions">
                    ${withdrawal.status === 'pending' ? `
                        <button class="btn-sm btn-success" onclick="adminManager.approveWithdrawal(${withdrawal.id})">Approve</button>
                        <button class="btn-sm btn-danger" onclick="adminManager.rejectWithdrawal(${withdrawal.id})">Reject</button>
                    ` : ''}
                    <button class="btn-sm btn-primary" onclick="adminManager.editWithdrawal(${withdrawal.id})">Details</button>
                </div>
            </div>
        `).join('');
        
        withdrawalsList.innerHTML = html;
    }

    filterWithdrawals() {
        const status = document.getElementById('withdrawalStatusFilter').value;
        const filteredWithdrawals = status ? 
            this.currentData.withdrawals.filter(withdrawal => withdrawal.status === status) :
            this.currentData.withdrawals;
        this.renderWithdrawals(filteredWithdrawals);
    }

    // Balance Management
    async adjustBalance() {
        const userId = document.getElementById('balanceUserId').value;
        const amount = parseFloat(document.getElementById('balanceAmount').value);
        const reason = document.getElementById('balanceReason').value;

        if (!userId || isNaN(amount) || !reason) {
            this.showMessage('Please fill all fields correctly', 'error');
            return;
        }

        const result = await this.apiCall({
            action: 'adjust_balance',
            user_id: userId,
            delta: amount,
            reason: reason
        });

        if (result && result.success) {
            document.getElementById('balanceUserId').value = '';
            document.getElementById('balanceAmount').value = '';
            document.getElementById('balanceReason').value = '';
            this.loadBalanceHistory();
            this.showMessage('Balance adjusted successfully', 'success');
        } else {
            this.showMessage(result?.message || 'Failed to adjust balance', 'error');
        }
    }

    async loadBalanceHistory() {
        const historyList = document.getElementById('balanceHistoryList');
        historyList.innerHTML = '<div class="loading">Loading balance history...</div>';

        const result = await this.apiCall({ action: 'get_balance_history' });
        if (result && result.success) {
            this.renderBalanceHistory(result.data);
        } else {
            historyList.innerHTML = '<div class="message error">Failed to load balance history</div>';
        }
    }

    renderBalanceHistory(history) {
        const historyList = document.getElementById('balanceHistoryList');
        if (!history || history.length === 0) {
            historyList.innerHTML = '<div class="message">No balance history found</div>';
            return;
        }

        const html = history.slice(0, 20).map(record => `
            <div class="list-item">
                <div class="item-info">
                    <div class="item-title">User ${record.user_id}: ${record.delta_amount > 0 ? '+' : ''}${record.delta_amount} ZST</div>
                    <div class="item-subtitle">Reason: ${record.reason}</div>
                    <div class="item-subtitle">Balance: ${record.previous_balance} → ${record.new_balance} ZST</div>
                    <div class="item-subtitle">Date: ${new Date(record.created_at).toLocaleString()}</div>
                </div>
                <div class="status-badge ${record.delta_amount > 0 ? 'status-approved' : 'status-pending'}">
                    ${record.delta_amount > 0 ? 'Credit' : 'Debit'}
                </div>
            </div>
        `).join('');
        
        historyList.innerHTML = html;
    }

    // System Settings
    async loadSettings() {
        const settingsForm = document.getElementById('settingsForm');
        settingsForm.innerHTML = '<div class="loading">Loading settings...</div>';

        const result = await this.apiCall({ action: 'get_settings' });
        if (result && result.success) {
            this.currentData.settings = result.data;
            this.renderSettings(result.data);
        } else {
            settingsForm.innerHTML = '<div class="message error">Failed to load settings</div>';
        }
    }

    renderSettings(settings) {
        const settingsForm = document.getElementById('settingsForm');
        
        const html = settings.map(setting => `
            <div class="form-group">
                <label for="setting_${setting.setting_key}">${setting.setting_key.replace(/_/g, ' ').toUpperCase()}</label>
                <input 
                    type="${setting.setting_type === 'number' ? 'number' : setting.setting_type === 'boolean' ? 'checkbox' : 'text'}"
                    id="setting_${setting.setting_key}"
                    value="${setting.setting_value}"
                    ${setting.setting_type === 'boolean' && setting.setting_value === 'true' ? 'checked' : ''}
                    ${setting.setting_type === 'number' ? 'step="0.01"' : ''}
                >
                <small>${setting.description || ''}</small>
            </div>
        `).join('');
        
        settingsForm.innerHTML = html + `
            <button type="button" onclick="adminManager.saveSettings()" class="btn-primary" style="width: 100%; margin-top: 1rem;">
                Save Settings
            </button>
        `;
    }

    async saveSettings() {
        const settings = {};
        this.currentData.settings.forEach(setting => {
            const input = document.getElementById(`setting_${setting.setting_key}`);
            if (input) {
                if (setting.setting_type === 'boolean') {
                    settings[setting.setting_key] = input.checked ? 'true' : 'false';
                } else {
                    settings[setting.setting_key] = input.value;
                }
            }
        });

        const result = await this.apiCall({
            action: 'update_settings',
            settings: settings
        });

        if (result && result.success) {
            this.showMessage('Settings saved successfully', 'success');
        } else {
            this.showMessage(result?.message || 'Failed to save settings', 'error');
        }
    }

    // Admin Logs
    async loadLogs() {
        const logsList = document.getElementById('logsList');
        logsList.innerHTML = '<div class="loading">Loading logs...</div>';

        const result = await this.apiCall({ action: 'get_logs' });
        if (result && result.success) {
            this.currentData.logs = result.data;
            this.renderLogs(result.data);
        } else {
            logsList.innerHTML = '<div class="message error">Failed to load logs</div>';
        }
    }

    renderLogs(logs) {
        const logsList = document.getElementById('logsList');
        if (!logs || logs.length === 0) {
            logsList.innerHTML = '<div class="message">No logs found</div>';
            return;
        }

        const html = logs.map(log => `
            <div class="list-item">
                <div class="item-info">
                    <div class="item-title">${log.action}</div>
                    <div class="item-subtitle">Admin: ${log.admin_username || log.admin_id}</div>
                    <div class="item-subtitle">Target User: ${log.target_user_id || 'N/A'}</div>
                    <div class="item-subtitle">Date: ${new Date(log.created_at).toLocaleString()}</div>
                    ${log.details ? `<div class="item-subtitle">Details: ${JSON.stringify(log.details)}</div>` : ''}
                </div>
            </div>
        `).join('');
        
        logsList.innerHTML = html;
    }

    // Modal Management
    editUser(userId) {
        const user = this.currentData.users.find(u => u.id === userId);
        if (!user) return;

        document.getElementById('modalTitle').textContent = 'Edit User';
        document.getElementById('modalBody').innerHTML = `
            <div class="form-group">
                <label>Username:</label>
                <input type="text" id="modalUsername" value="${user.username}">
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" id="modalEmail" value="${user.email}">
            </div>
            <div class="form-group">
                <label>Status:</label>
                <select id="modalStatus">
                    <option value="active" ${user.status === 'active' ? 'selected' : ''}>Active</option>
                    <option value="suspended" ${user.status === 'suspended' ? 'selected' : ''}>Suspended</option>
                    <option value="banned" ${user.status === 'banned' ? 'selected' : ''}>Banned</option>
                </select>
            </div>
        `;
        
        document.getElementById('modalSaveBtn').onclick = () => this.saveUserEdit(userId);
        document.getElementById('editModal').style.display = 'block';
    }

    async saveUserEdit(userId) {
        const result = await this.apiCall({
            action: 'update_user',
            user_id: userId,
            username: document.getElementById('modalUsername').value,
            email: document.getElementById('modalEmail').value,
            status: document.getElementById('modalStatus').value
        });

        if (result && result.success) {
            this.closeModal();
            this.loadUsers();
            this.showMessage('User updated successfully', 'success');
        } else {
            this.showMessage(result?.message || 'Failed to update user', 'error');
        }
    }

    closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    showMessage(message, type) {
        // Create a temporary message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.textContent = message;
        messageDiv.style.position = 'fixed';
        messageDiv.style.top = '20px';
        messageDiv.style.right = '20px';
        messageDiv.style.zIndex = '1001';
        messageDiv.style.maxWidth = '300px';
        
        document.body.appendChild(messageDiv);
        
        // Remove after 5 seconds
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 5000);
    }
}

// Initialize admin manager when page loads
window.addEventListener('DOMContentLoaded', () => {
    window.adminManager = new AdminManager();
});