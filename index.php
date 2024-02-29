<?php
session_start();
include "admin/connection.php";
?>

<?php
$msg_customer = " ";
if(isset($_POST["submit_customer"])){
    
    $email = $_POST['email'];
    $password = $_POST['pass'];

    $sql = "SELECT * FROM Customers WHERE CEmail='$email' AND CPassword='$password'; ";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        if($row = $result->fetch_assoc()) {
          $_SESSION["CustomerID"] = $row["CustomerID"];
          $_SESSION["ContactName"] = $row["ContactName"];
          //header("location:admin/catalog.php?filterby=0");
          echo'<script> 			window.location.replace("admin/catalog.php?filterby=0"); 		</script>';

          
        } else {
            $msg_customer = '<div class="alert alert-dismissible alert-danger">
            <strong>Oh snap!</strong> Invalid E-mail / Password. Change some things up and try submitting again.
          </div>
          ';
        }
    }
    else{
        $msg_customer = '<div class="alert alert-dismissible alert-danger">
        <strong>Oh snap!</strong> Invalid E-mail / Password. Change some things up and try submitting again.
      </div>';
    }
}

$msg_admin = " ";
if(isset($_POST["submit_admin"])){
    
    $email = $_POST['email'];
    $password = $_POST['pass'];

    $sql = "SELECT * FROM users_tb WHERE email='$email' AND pass='$password'; ";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        if($row = $result->fetch_assoc()) {
            header("location:admin/user_list.php");
        } else {
            $msg_admin = '<div class="alert alert-dismissible alert-danger">
            <strong>Oh snap!</strong> Invalid E-mail / Password. Change some things up and try submitting again.
          </div>';
        }
    }
    else{
        $msg_admin = '<div class="alert alert-dismissible alert-danger">
        <strong>Oh snap!</strong> Invalid E-mail / Password. Change some things up and try submitting again.
      </div>';
    }
}
$conn->close();
?>

<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>M-A-E-Commerce</title>
        <!-- Favicon-->
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
        <!-- Font Awesome icons (free version)-->
        <script src="https://use.fontawesome.com/releases/v5.15.3/js/all.js" crossorigin="anonymous"></script>
        <!-- Google fonts-->
        <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700" rel="stylesheet" type="text/css" />
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body id="page-top">
        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
            <div class="container">
                <a class="navbar-brand" href="#page-top"><img src="assets/img/logo-mae.png" alt="..." /></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    Menu
                    <i class="fas fa-bars ms-1"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav text-uppercase ms-auto py-4 py-lg-0">
                        <li class="nav-item"><a class="nav-link" href="#portfolio">Highlights</a></li>
                        <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                        <li class="nav-item"><a class="nav-link" href="#login">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                        
                    </ul>
                </div>
            </div>
        </nav>
        <!-- Masthead-->
        <header class="masthead">
            <div class="container">
                <div class="masthead-subheading">Welcome To M-A-E-Commerce!</div>
                <div class="masthead-heading text-uppercase">Most Known to Mankind!</div>
                <a class="btn btn-primary btn-xl text-uppercase" href="#services">Tell Me More, Mankind!</a>
            </div>
        </header>
        
        <!-- Portfolio Grid-->
        <section class="page-section bg-light" id="portfolio">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Catalog Highlights</h2>
                    <h3 class="section-subheading text-muted">Featuring the Most Affordable Items known to Mankind!</h3>
                </div>
                <div class="row">
                    <div class="col-lg-4 col-sm-6 mb-4">
                        <!-- Portfolio item 1-->
                        <div class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#portfolioModal1">
                                <div class="portfolio-hover">
                                    <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                </div>
                                <img class="img-fluid" src="assets/img/portfolio/mimikyu.jpg" alt="..." />
                            </a>
                            <div class="portfolio-caption">
                                <div class="portfolio-caption-heading">Pokemon PlayMo Mimikyu</div>
                                <div class="portfolio-caption-subheading text-muted">Toys</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6 mb-4">
                        <!-- Portfolio item 2-->
                        <div class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#portfolioModal2">
                                <div class="portfolio-hover">
                                    <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                </div>
                                <img class="img-fluid" src="assets/img/portfolio/rayquaza.jpg" alt="..." />
                            </a>
                            <div class="portfolio-caption">
                                <div class="portfolio-caption-heading">Pokemon PlaMo Rayquaza</div>
                                <div class="portfolio-caption-subheading text-muted">Toys</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6 mb-4">
                        <!-- Portfolio item 3-->
                        <div class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#portfolioModal3">
                                <div class="portfolio-hover">
                                    <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                </div>
                                <img class="img-fluid" src="assets/img/portfolio/mew.jpg" alt="..." />
                            </a>
                            <div class="portfolio-caption">
                                <div class="portfolio-caption-heading">Pokemon PlaMo Mew</div>
                                <div class="portfolio-caption-subheading text-muted">Toys</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6 mb-4 mb-lg-0">
                        <!-- Portfolio item 4-->
                        <div class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#portfolioModal4">
                                <div class="portfolio-hover">
                                    <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                </div>
                                <img class="img-fluid" src="assets/img/portfolio/eevee.jpg" alt="..." />
                            </a>
                            <div class="portfolio-caption">
                                <div class="portfolio-caption-heading">Pokemon PlaMo Eevee</div>
                                <div class="portfolio-caption-subheading text-muted">Toys</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6 mb-4 mb-sm-0">
                        <!-- Portfolio item 5-->
                        <div class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#portfolioModal5">
                                <div class="portfolio-hover">
                                    <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                </div>
                                <img class="img-fluid" src="assets/img/portfolio/pikachu.jpg" alt="..." />
                            </a>
                            <div class="portfolio-caption">
                                <div class="portfolio-caption-heading">Pokemon PlaMo Pikachu</div>
                                <div class="portfolio-caption-subheading text-muted">Toys</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6">
                        <!-- Portfolio item 6-->
                        <div class="portfolio-item">
                            <a class="portfolio-link" data-bs-toggle="modal" href="#portfolioModal6">
                                <div class="portfolio-hover">
                                    <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                </div>
                                <img class="img-fluid" src="assets/img/portfolio/6.jpg" alt="..." />
                            </a>
                            <div class="portfolio-caption">
                                <div class="portfolio-caption-heading">Window</div>
                                <div class="portfolio-caption-subheading text-muted">Photography</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
                <!-- Team-->
        <section class="page-section bg-light" id="team">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">The team</h2>
                    <h3 class="section-subheading text-muted">The builder(s) of The Most Affordable E-commerce platform known to mankind.</h3>
                </div>
                <div class="row">
                    <div class="col-lg-4">
                        <div class="team-member">
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="team-member">
                            <img class="mx-auto rounded-circle"  />
                            <h4>Nealle Benedict Adisaputro</h4>
                            <p class="text-muted">Full Stack Web Developer</p>
                            <a class="btn btn-dark btn-social mx-2" href="#!"><i class="fab fa-twitter"></i></a>
                            <a class="btn btn-dark btn-social mx-2" href="#!"><i class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-dark btn-social mx-2" href="https://www.linkedin.com/in/nealle-benedict-adisaputro-a549681b7/!"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="team-member">
                            
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-8 mx-auto text-center"><p class="large text-muted">This is a personal website. Well, you should expect.</p></div>
                </div>
            </div>
        </section>
        <!--Login-->
        <section class="page-section" id="login">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Login</h2>
                    <h3 class="section-subheading text-muted">Login Or <a href='admin/Customers_insert.php'>Register Now</a> and start shopping in the most affordable e-commerce platform
                        known to mankind!</h3>
                </div>
                <!-- * * * * * * * * * * * * * * *-->
                <!-- * * SB Forms Contact Form * *-->
                <!-- * * * * * * * * * * * * * * *-->
                <!-- This form is pre-integrated with SB Forms.-->
                <!-- To make this form functional, sign up at-->
                <!-- https://startbootstrap.com/solution/contact-forms-->
                <!-- to get an API token!-->
                <div class="row align-items-stretch mb-5">
                <div class="col-md-2"></div>
                <div class="col-md-8">
                    <form action="index.php#login" method="post" class="">
                      <div class="form-group">
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="floatingInput" placeholder="Enter Email" name="email">
                            <label for="floatingInput">example@email.com</label>
                        </div>
                      <div class="form-group">
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="floatingInput" placeholder="Password Here" name="pass">
                            <label for="floatingInput">Password</label>
                        </div>

                      </div>
                        <?php
                            echo $msg_customer ;
                        ?>
                      <div class="text-center"><button type="submit" name="submit_customer" class="btn btn-primary btn-xl text-uppercase">LOGIN</button></div>
                    </form>
                </div>
                
                <div class="col-md-2"></div>
                </div>
                <div class="row align-items-stretch mb-5">
                <div class="col-md-2"></div>
                <div class="col-md-8">
                    <div class="text-center">
                        <p><br></p>
                        <h2 class="section-heading text-uppercase">You're An Admin?</h2>
                        <h3 class="section-subheading text-muted">Enter your credentials and start Administering the most affordable e-commerce platform
                            known to mankind!</h3>
                    </div>
                    <form action="index.php#login" method="post">
                      <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="floatingInput" placeholder="Enter Email" name="email">
                        <label for="floatingInput">example@email.com</label>
                      </div>
                      <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="floatingInput" placeholder="Password Here" name="pass">
                        <label for="floatingInput">Password</label>
                      </div>                    
                        <?php
                        echo $msg_admin ;
                        ?>
                      <div class="text-center"><button type="submit" name="submit_admin" class="btn btn-primary btn-xl text-uppercase">LOGIN</button></div>
                    </form>

                </div>
                <div class="col-md-2"></div>
                </div>
            </div>
        </section>
        
        <!-- Footer-->
        <footer class="footer py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-4 text-lg-start">Copyright &copy; MAE Ecommerce 2021</div>
                    <div class="col-lg-4 my-3 my-lg-0">
                        <a class="btn btn-dark btn-social mx-2" href="#!"><i class="fab fa-twitter"></i></a>
                        <a class="btn btn-dark btn-social mx-2" href="#!"><i class="fab fa-facebook-f"></i></a>
                        <a class="btn btn-dark btn-social mx-2" href="#!"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a class="link-dark text-decoration-none me-3" href="#!">Privacy Policy</a>
                        <a class="link-dark text-decoration-none" href="#!">Terms of Use</a>
                    </div>
                </div>
            </div>
        </footer>
        <!-- Portfolio Modals-->
        <!-- Portfolio item 1 modal popup-->
        <div class="portfolio-modal modal fade" id="portfolioModal1" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Close modal" /></div>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="modal-body">
                                    <!-- Project details-->
                                    <h2 class="text-uppercase">mimikyu</h2>
                                    <p class="item-intro text-muted">The Mimic Pokemon comes to MAE.</p>
                                    <img class="img-fluid d-block mx-auto" src="assets/img/portfolio/mimikyu.jpg" alt="..." />
                                    <p>A fan favorite to some degree, both due to its cuteness and its prowess in battle. Now it can be built by your own hands and be put anywhere in your room where it might be entertaining unsuspecting victims!</p>
                                    <ul class="list-inline">
                                        <li>
                                            <strong>Price</strong>
                                            Rp.120,000.-
                                        </li>
                                        <li>
                                            <strong>Category:</strong>
                                            TOYS
                                        </li>
                                    </ul>
                                    <button class="btn btn-primary btn-xl text-uppercase" data-bs-dismiss="modal" type="button">
                                        <i class="fas fa-times me-1"></i>
                                        Close Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Portfolio item 2 modal popup-->
        <div class="portfolio-modal modal fade" id="portfolioModal2" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Close modal" /></div>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="modal-body">
                                    <!-- Project details-->
                                    <h2 class="text-uppercase">Rayquaza</h2>
                                    <p class="item-intro text-muted">Liven up the skies in your room with the Sky High Pokemon!</p>
                                    <img class="img-fluid d-block mx-auto" src="assets/img/portfolio/rayquaza.jpg" alt="..." />
                                    <p>A fan favorite amongst all other legendaries, Rayquaza combines elegance and display of strength to add to your collection!</p>
                                    <ul class="list-inline">
                                        <li>
                                            <strong>Price:</strong>
                                            Rp.250,000.-
                                        </li>
                                        <li>
                                            <strong>Category:</strong>
                                            Toys
                                        </li>
                                    </ul>
                                    <button class="btn btn-primary btn-xl text-uppercase" data-bs-dismiss="modal" type="button">
                                        <i class="fas fa-times me-1"></i>
                                        Close Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Portfolio item 3 modal popup-->
        <div class="portfolio-modal modal fade" id="portfolioModal3" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Close modal" /></div>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="modal-body">
                                    <!-- Project details-->
                                    <h2 class="text-uppercase">MEW</h2>
                                    <p class="item-intro text-muted">A Pokemon that has trace of every other Pokemon's DNA in theirs.</p>
                                    <img class="img-fluid d-block mx-auto" src="assets/img/portfolio/mew.jpg" alt="..." />
                                    <p>The cuteness of Mew will improve any kind of collection!</p>
                                    <ul class="list-inline">
                                        <li>
                                            <strong>Price:</strong>
                                            Rp.150,000,-
                                        </li>
                                        <li>
                                            <strong>Category:</strong>
                                            Toys
                                        </li>
                                    </ul>
                                    <button class="btn btn-primary btn-xl text-uppercase" data-bs-dismiss="modal" type="button">
                                        <i class="fas fa-times me-1"></i>
                                        Close Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Portfolio item 4 modal popup-->
        <div class="portfolio-modal modal fade" id="portfolioModal4" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Close modal" /></div>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="modal-body">
                                    <!-- Project details-->
                                    <h2 class="text-uppercase">EEVEE</h2>
                                    <p class="item-intro text-muted">The sleeping Eevee will make anyone that sees it goes aww.</p>
                                    <img class="img-fluid d-block mx-auto" src="assets/img/portfolio/eevee.jpg" alt="..." />
                                    <p>Pokemon Play Model Eevee in sleeping position. Put it somewhere in your room or your house, and it sures add cuteness to it.</p>
                                    <ul class="list-inline">
                                        <li>
                                            <strong>Price:</strong>
                                            Rp.140,000.-
                                        </li>
                                        <li>
                                            <strong>Category:</strong>
                                            Toys
                                        </li>
                                    </ul>
                                    <button class="btn btn-primary btn-xl text-uppercase" data-bs-dismiss="modal" type="button">
                                        <i class="fas fa-times me-1"></i>
                                        Close Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Portfolio item 5 modal popup-->
        <div class="portfolio-modal modal fade" id="portfolioModal5" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Close modal" /></div>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="modal-body">
                                    <!-- Project details-->
                                    <h2 class="text-uppercase">Pikachu</h2>
                                    <p class="item-intro text-muted">Who don't know Pikachu?</p>
                                    <img class="img-fluid d-block mx-auto" src="assets/img/portfolio/pikachu.jpg" alt="..." />
                                    <p>Let's be honest here -  no Pokemon PlaMo collection is complete without the official Pokemon mascot Pikachu!</p>
                                    <ul class="list-inline">
                                        <li>
                                            <strong>Price:</strong>
                                            Rp.150,000.-
                                        </li>
                                        <li>
                                            <strong>Category:</strong>
                                            Toys
                                        </li>
                                    </ul>
                                    <button class="btn btn-primary btn-xl text-uppercase" data-bs-dismiss="modal" type="button">
                                        <i class="fas fa-times me-1"></i>
                                        Close Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Portfolio item 6 modal popup-->
        <div class="portfolio-modal modal fade" id="portfolioModal6" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Close modal" /></div>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="modal-body">
                                    <!-- Project details-->
                                    <h2 class="text-uppercase">Project Name</h2>
                                    <p class="item-intro text-muted">Lorem ipsum dolor sit amet consectetur.</p>
                                    <img class="img-fluid d-block mx-auto" src="assets/img/portfolio/6.jpg" alt="..." />
                                    <p>Use this area to describe your project. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Est blanditiis dolorem culpa incidunt minus dignissimos deserunt repellat aperiam quasi sunt officia expedita beatae cupiditate, maiores repudiandae, nostrum, reiciendis facere nemo!</p>
                                    <ul class="list-inline">
                                        <li>
                                            <strong>Client:</strong>
                                            Window
                                        </li>
                                        <li>
                                            <strong>Category:</strong>
                                            Photography
                                        </li>
                                    </ul>
                                    <button class="btn btn-primary btn-xl text-uppercase" data-bs-dismiss="modal" type="button">
                                        <i class="fas fa-times me-1"></i>
                                        Close Project
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Bootstrap core JS-->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Core theme JS-->
        <script src="js/scripts.js"></script>
        <!-- * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *-->
        <!-- * *                               SB Forms JS                               * *-->
        <!-- * * Activate your form at https://startbootstrap.com/solution/contact-forms * *-->
        <!-- * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *-->
        <script src="https://cdn.startbootstrap.com/sb-forms-latest.js"></script>
    </body>
</html>
