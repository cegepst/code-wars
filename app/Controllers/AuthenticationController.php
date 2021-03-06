<?php namespace Controllers;

use Models\Brokers\TokenBroker;
use Models\Logger;
use Zephyrus\Application\Flash;

class AuthenticationController extends Controller
{
    public function initializeRoutes()
    {
        $this->get('/login', 'showLogin');
        $this->get('/logout', 'logout');

        $this->post('/login', 'processLogin');
    }

    public function showLogin()
    {
        if ($this->isLogged()) {
            return $this->redirect('/');
        }
        return $this->render('authentication/login');
    }

    public function processLogin()
    {
        $logger = new Logger();
        $logger->loginWithForm($this->buildForm());
        if ($logger->hasSucceeded()) {
            $logger->logUser();
            return $this->redirect('/');
        }
        sleep(2);
        Flash::error($logger->getErrorMessage());
        return $this->redirect('/login');
    }

    public function logout()
    {
        session_destroy();
        if (isset($_COOKIE[REMEMBER_ME])) {
            $this->unRememberMe();
        }
        return $this->redirect('/');
    }

    private function unRememberMe()
    {
        $broker = new TokenBroker();
        $broker->delete($_COOKIE[REMEMBER_ME]);
        setcookie(REMEMBER_ME, '', 1, '/');
        unset($_COOKIE[REMEMBER_ME]);
    }
}
