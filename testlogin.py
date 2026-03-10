import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# --- CONFIGURATION ---
BASE_URL = "http://localhost/Caravan%20of%20Flavours"
HOME_PAGE = f"{BASE_URL}/frontend/index.html"
FARMER_EMAIL = "farmer@gmail.com"
FARMER_PASS = "12345678"

def test_farmer_login():
    # Simplified driver initialization (Latest Selenium handles this automatically)
    driver = webdriver.Chrome() 
    
    try:
        print(f"🚀 Navigating to Home Page...")
        driver.get(HOME_PAGE)
        driver.maximize_window()

        # Wait for Sign In button
        wait = WebDriverWait(driver, 10)
        sign_in_btn = wait.until(EC.element_to_be_clickable((By.XPATH, "//button[contains(text(), 'Sign In')]")))
        sign_in_btn.click()

        # Wait for Login fields
        email_input = wait.until(EC.presence_of_element_located((By.ID, "login-email")))
        pass_input = driver.find_element(By.ID, "login-password")
        
        email_input.send_keys(FARMER_EMAIL)
        pass_input.send_keys(FARMER_PASS)

        # Click Login
        login_submit_btn = driver.find_element(By.XPATH, "//button[contains(text(), 'SIGN IN')]")
        login_submit_btn.click()

        # Wait for dashboard
        wait.until(EC.url_contains("farmer-dashboard.html"))
        print(f"✅ SUCCESS: Logged in! Current URL: {driver.current_url}")

    except Exception as e:
        print(f"⚡ ERROR: {str(e)}")
    finally:
        time.sleep(3)
        driver.quit()

if __name__ == "__main__":
    test_farmer_login()
