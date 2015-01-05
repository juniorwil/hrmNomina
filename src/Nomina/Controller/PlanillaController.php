<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;
use Zend\Db\Adapter\Driver\ConnectionInterface;

use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Principal\Model\NominaFunc;        // Libreria de funciones nomina
use Principal\Model\PlanillaFunc;        // Libreria de funciones planilla unica
use Principal\Model\Gplanilla; // Procesos generacion de planilla

use Nomina\Model\Entity\Planilla; // (C)


class PlanillaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/planilla/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Planillas activas"; // Titulo listado
    private $tfor = "Generación de planilla unica"; // Titulo formulario
    private $ttab = "Periodo, Grupo, Empleados ,Estado, Documento, Int. salud,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
        "datos"     =>  $d->getGeneral("select a.id,a.fecha,a.ano,a.mes,b.nombre as nomgrup, a.estado,a.numEmp
                                        from n_planilla_unica a 
					    inner join n_grupos b on a.idGrupo=b.id                                         
                                        where a.estado in (0,1)"),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin,
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
   // Editar y nuevos datos *********************************************************************************************
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Grupo de nomina
      $arreglo='';
      $datos = $d->getGrupo(' and activa=0'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idGrupo")->setValueOptions($arreglo);                         
      // Tipos de calendario
      $arreglo='';
      $datos = $d->getTnom(' and activa=0'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);                                                 
      //       
      $datos=0;
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'datos'   => $datos,  
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario 
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('idGrupo'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Planilla($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Consultar fechas del calendario
                $d=new AlbumTable($this->dbAdapter);
                $p=new Gplanilla($this->dbAdapter);
                // Buscar periodo activo
                $datos = $d->getGeneral1('select ano, mes +1 as mes  
                               from n_planilla_unica_h where estado = 0
                                order by ano, mes desc');
                $ano = $datos['ano'];$mes = $datos['mes'];
                // INICIO DE TRANSACCIONES
                $connection = null;
                try {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
   	            $connection->beginTransaction();                
                    // Generacion cabecera
                    $id = $u->actRegistro($data, $ano, $mes);
                    // Generacion empleados
                    $p->getNominaE($id,$data->idGrupo);  // Generacion de empleados    
                    
                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);                    
                }// Fin try casth   
                catch (\Exception $e) {
    	            if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
     	                $connection->rollback();
                        echo $e;
 	            }	
 	            /* Other error handling */
                }// FIN TRANSACCION                                   

            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Nomina($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $n = $datos['nombre'];
            // Valores guardados
            $form->get("nombre")->setAttribute("value","$n"); 
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 
  
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d=new AlbumTable($this->dbAdapter); 
            // Consultar nomina
            // INICIO DE TRANSACCIONES
            $connection = null;
            try {
               $connection = $this->dbAdapter->getDriver()->getConnection();
   	       $connection->beginTransaction();
               // REGISTRO LIBRO DE CESANTIAS
               //$c->delRegistro($id); 
               // Borrar tablas inferiores               
               $datos = $d->modGeneral("delete from n_planilla_unica_e where idPla=".$id);                
               $datos = $d->modGeneral("delete from n_planilla_unica where id=".$id);                              
               $connection->commit();
            }// Fin try casth   
            catch (\Exception $e) {
    	        if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
     	           $connection->rollback();
                   echo $e;
 	        }	
 	        /* Other error handling */
            }// FIN TRANSACCION                    
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }
   //----------------------------------------------------------------------------------------------------------
   // GENERACION PLANILLA UNICA -------------------------------------------------------------------------------
   //----------------------------------------------------------------------------------------------------------
    public function listgAction()
    {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);       
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);             
            
      $valores=array
      (
        "form"    => $form,
        'url'     => $this->getRequest()->getBaseUrl(),          
        "titulo"  => $this->tlis,
        "datos"   => $d->getGeneral("select b.id, a.CedEmp, a.nombre,a.apellido, a.idVac ,
                       c.nombre as nomCar, d.nombre as nomCcos, b.incluido, e.fechaI, e.fechaF                        
                       from a_empleados a inner join n_nomina_e b on a.id=b.idEmp 
                       left join t_cargos c on c.id=a.idCar
                       inner join n_cencostos d on d.id=a.idCcos
                       left join n_vacaciones e on e.id=b.idVac and e.estado=1 
                       where b.idNom=".$id) ,
        "lin"     => $this->lin
      );                        
      return new ViewModel($valores);
    }       

    // GENERACION PLANILLA UNICA GENERAL-------------------------------------
    public function listpAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
         $request = $this->getRequest();   
         $data = $this->request->getPost();                    
         $id = $data->id; // ID de la nomina                  
         $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
         $d = new AlbumTable($this->dbAdapter);                 
         $f = new PlanillaFunc($this->dbAdapter);  
         $g = new Gplanilla($this->dbAdapter);           
         // INICIO DE TRANSACCIONES
         $connection = null;
         try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
	 	$connection->beginTransaction();
            $sw=1;
            if ($sw==1) 
            {
               $datos = $d->getGeneral("select * from n_planilla_unica_e where idPla = ".$data->id);
               $idPla = $data->id;
               foreach ($datos as $dat)
               {             
                   $id = $dat['id'];
                   $idEmp = $dat['idEmp'];
                   // 1. DIAS SALUD
                   $datF = $f->getDiasEmp($idPla, $idEmp);
                   $campo = 'diasSalud';
                   $g->getPlanillaE($id, $campo, $datF['valor'] );                                    
                                      
                   // 2. DIAS PENSION
                   $datF = $f->getDiasEmp($idPla, $idEmp);
                   $campo = 'diasPension';
                   $g->getPlanillaE($id, $campo, $datF['valor'] );
                   
                   // 3. DIAS RIESGOS
                   $datF = $f->getDiasEmp($idPla, $idEmp);
                   $campo = 'diasRiesgos';
                   $valor = $datF['valor'];
                      // Buscar dias de incapacidad par restar
                      $datProv = $f->getEstados($idPla, $idEmp);
                      if ($datProv['diasInc']>0)
                         $valor = $valor - $datProv['diasInc'];                                      
                   $g->getPlanillaE($id, $campo, $valor );                   

                   
                   // 4. IBC SALUD
                   $datF = $f->getLey($idPla, $idEmp);
                   $campo = 'ibcSalud';
                   $valor = round($datF['valor'],2) ;
                   $g->getPlanillaE($id, $campo, $valor );                                      
                   
                   // 5. FONDO DE SALUD
                   $datF = $d->getEmp(" and id =".$idEmp );
                   foreach($datF as $dat)
                   {
                       $valor = $dat['idFsal'];
                   }
                   $campo = 'idFonS';
                   $g->getPlanillaE($id, $campo, $valor );                                                         
                   
                   // 6. APORTE POR SALUD
                   $datProv = $d->getProviciones(' and nombre=5 ');
                   $valor =  $datProv['por'].' * ibcSalud';
                   $campo = 'aporSalud';
                   $g->getPlanillaE($id, $campo, $valor );                                                                            

                   
                   // 7. IBC PENSION
                   $datF = $f->getLey($idPla, $idEmp);
                   $campo = 'ibcPension';
                   $valor = round($datF['valor'],2) ;
                   $g->getPlanillaE($id, $campo, $valor );                                      
                   
                   // 8. FONDO DE PENSION
                   $datF = $d->getEmp(" and id =".$idEmp );
                   foreach($datF as $dat)
                   {
                       $valor = $dat['idFpen'];
                   }
                   $campo = 'idFonP';
                   $g->getPlanillaE($id, $campo, $valor );                                                         
                   
                   // 9. APORTE POR PENSION
                   $datProv = $d->getProviciones(' and nombre=6 ');
                   $valor = $datProv['por'].' * ibcPension';
                   $campo = 'aporPension';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                               
                   
                   
                   // 10. Fondos de solidaridad                   
                   $datF = $f->getSolidaridad($idPla, $idEmp);
                   $valor = $datF['valor'];
                   $campo = 'aporSolidaridad';
                   IF ($campo!='')
                      $g->getPlanillaE($id, $campo, $valor );                                                                                                                  
                   
                   // 11. IBC RIESGOS
                   $datF = $f->getLey($idPla, $idEmp);
                   $campo = 'ibcRiesgos';
                   $valor = round($datF['valor'],2) ;
                   $datF = $f->getLeyRl($idPla, $idEmp); // Se buscan los valores descontados por incapacidades
                   if ($datF['valor']>0)
                      $valor = $valor - round($datF['valor'],2) ;
                   
                   $g->getPlanillaE($id, $campo, $valor );                                      
                   
                   
                   // 12. TARIFA ARL 
                   $datF = $d->getEmpM(" and a.id =".$idEmp );
                   $porArl = 0;
                   foreach($datF as $dat)
                   {
                       $valor = $dat['porc'];
                       $porArl = $dat['porc']/100;
                   }
                   $campo = 'tarifaArl';
                   $g->getPlanillaE($id, $campo, $valor );                                                         
                    
                    
                   // 13. FONDOS RIESGOS ARL
                   $datF = $d->getEmp(" and id =".$idEmp );
                   foreach($datF as $dat)
                   {
                       $valor = $dat['idFarp'];
                   }
                   $campo = 'idFonR';
                   $g->getPlanillaE($id, $campo, $valor );                                                                            
                   
                   // 14. APORTES RIESGOS ARL
                   $valor = $porArl.' * ibcRiesgos';
                   $campo = 'aporRiesgos';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                                                  
                   
                   // 15. IBC CAJA
                   $datF = $f->getCaja($idPla, $idEmp);
                   $campo = 'ibcCaja';
                   $valor = round($datF['valor'],2) ;
                   $g->getPlanillaE($id, $campo, $valor );                                                         
                   
                   // 16. FONDOS CAJA DE COMPENSACION 
                   $datF = $d->getEmp(" and id =".$idEmp );
                   foreach($datF as $dat)
                   {
                       $valor = $dat['idCaja'];
                   }
                   $campo = 'idCaja';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                               
                   
                   // 17. APORTE POR CAJA DE COMPENSACION
                   $datProv = $d->getProviciones(' and nombre=7 ');
                   $valor = $datProv['por'].' * ibcCaja';
                   $campo = 'aporCaja';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                                                  
                   
                   
                   // 18. APORTE POR SENA
                   $datProv = $d->getProviciones(' and nombre=8 ');
                   $valor = $datProv['por'].' * ibcCaja';
                   $campo = 'aporSena';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                                                  
                   
                   // 19. APORTE POR ICBF
                   $datProv = $d->getProviciones(' and nombre=9 ');
                   $valor = $datProv['por'].' * ibcCaja';
                   $campo = 'aporIcbf';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                                                                                        
                   
                   
                   // 20. REGISTRO DE INCAPACIDAD 
                   $datProv = $f->getEstados($idPla, $idEmp);
                   $valor = $datProv['diasInc'];                   
                   $campo = 'nInca';
                   $g->getPlanillaEr($id, $campo, $valor );
                   

                   // 22. REGISTRO DE INGRESO
                   $datProv = $f->getEstados($idPla, $idEmp);
                   $valor = $datProv['ingre'];                   
                   $campo = 'nIngreso';
                   $g->getPlanillaEr($id, $campo, $valor );                   

                   // 23. VST
                   $datF = $f->getLeyNs($idPla, $idEmp);
                   $campo = 'nVst';
                   $valor = 0;
                   if ($datF['valor']>0)
                       $valor = 1;
                   $g->getPlanillaE($id, $campo, $valor );                                                         
                   
                } // FIN REGISTRO DE PLANILLA UNICA
                $d->modGeneral("update n_planilla_unica set estado = 1 where id = ".$idPla);
            }// Sw e prueba ojo
            
            $connection->commit();
          }// Fin try casth   
          catch (\Exception $e) {
	    if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
   	        $connection->rollback();
                echo $e;
	   }
	/* Other error handling */
        }// FIN TRANSACCION        
                 
      }        
      
      $view = new ViewModel();        
      $this->layout('layout/blanco'); // Layout del login
      return $view;              
      
    } // Fin generacion nomina
    
    // Validar que la nomina no este generada ********************************************************************************************
    public function listvpAction()
    {
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();   
        if ($request->isPost()) {            
           $data = $this->request->getPost();                    
           $id = $data->id; // ID de la nomina                          
           $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
           $d=new AlbumTable($this->dbAdapter);         
           $datos = $d->getGeneral1("select estado from n_planilla_unica where id=".$id);
           $valido = '';
           if ($datos['estado']==1)
               $valido = 1;
           $valores=array
           (
            "valido"  =>  $valido,
           );                
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;           
        }
      }
    } // Fin listar registros     

    // Listado de planilla ********************************************************************************************
    public function listiAction()
    {
      $form = new Formulario("form");  
      $id = (int) $this->params()->fromRoute('id', 0);  
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  "Planilla N°".$id,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
        "datos"     =>  $d->getGeneral("select a.fecha, a.ano, a.mes, b.*,
                            c.CedEmp, c.nombre as nomEmp, c.apellido, d.nombre as nomCcos, 
                            e.nombre as nomCar 
                            from n_planilla_unica a 
                                inner join n_planilla_unica_e b on b.idPla = a.id 
                                inner join a_empleados c on c.id = b.idEmp 
                                inner join n_cencostos d on d.id = c.idCcos
                                inner join t_cargos e on e.id = c.idCar
                                order by d.nombre, c.nombre"),            
        "ttablas"   =>  "Empleado,Dias salud, Dias pension,Dias riesgos, IBC Salud,"
          . "Aporte salud, IBC Pension, Aporte de pension, Aporte de solidaridad, IBC Riesgos, Tarifa Arl, Aporte riesgos, IBC Caja,"
          . "Aporte caja, Aporte Sena, Aporte Icbf, Inca, Vaca, Aus, Retiro, Ingreso, Vst, Vsp ",
        "lin"       =>  $this->lin,
        "form"       =>  $form,  
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado

      );                
      return new ViewModel($valores);
        
    } // Fin listar registros     
}
