import { Routes, Route } from 'react-router-dom'
import LandingPage from './pages/public/LandingPage'
import StaffLogin from './pages/staff/StaffLogin'
import StaffDashboard from './pages/staff/StaffDashboard'
import StaffCustomers from './pages/staff/StaffCustomers'
import StaffLoyalty from './pages/staff/StaffLoyalty'
import StaffOrders from './pages/staff/StaffOrders'
import StaffLoadStatus from './pages/staff/StaffLoadStatus'
import StaffPickups from './pages/staff/StaffPickups'
import StaffPayments from './pages/staff/StaffPayments'
import StaffReports from './pages/staff/StaffReports'
import StaffAccount from './pages/staff/StaffAccount'
import CustomerLogin from './pages/customer/CustomerLogin'
import CustomerRegister from './pages/customer/CustomerRegister'
import CustomerDashboard from './pages/customer/CustomerDashboard'
import CustomerOrderStatus from './pages/customer/CustomerOrderStatus'
import CustomerLoyalty from './pages/customer/CustomerLoyalty'
import CustomerOrders from './pages/customer/CustomerOrders'
import CustomerPayments from './pages/customer/CustomerPayments'
import CustomerPickup from './pages/customer/CustomerPickup'
import CustomerProfile from './pages/customer/CustomerProfile'
import CustomerLayout from './components/CustomerLayout'
import StaffLayout from './components/StaffLayout'

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />

      <Route path="/staff-login" element={<StaffLogin />} />
      <Route path="/customer-login" element={<CustomerLogin />} />
      <Route path="/customer-register" element={<CustomerRegister />} />

      {/* All logged-in staff pages share the same layout */}
      <Route element={<StaffLayout />}>
        <Route path="/staff/dashboard" element={<StaffDashboard />} />
        <Route path="/staff/customers" element={<StaffCustomers />} />
        <Route path="/staff/loyalty" element={<StaffLoyalty />} />
        <Route path="/staff/orders" element={<StaffOrders />} />
        <Route path="/staff/load-status" element={<StaffLoadStatus />} />
        <Route path="/staff/pickups" element={<StaffPickups />} />
        <Route path="/staff/payments" element={<StaffPayments />} />
        <Route path="/staff/reports" element={<StaffReports />} />
        <Route path="/staff/account" element={<StaffAccount />} />
      </Route>

      {/* All logged-in customer pages share the same layout */}
      <Route element={<CustomerLayout />}>
        <Route path="/customer/dashboard" element={<CustomerDashboard />} />
        <Route path="/customer/order-status" element={<CustomerOrderStatus />} />
        <Route path="/customer/loyalty" element={<CustomerLoyalty />} />
        <Route path="/customer/orders" element={<CustomerOrders />} />
        <Route path="/customer/payments" element={<CustomerPayments />} />
        <Route path="/customer/pickup" element={<CustomerPickup />} />
        <Route path="/customer/profile" element={<CustomerProfile />} />
      </Route>
    </Routes>
  )
}
