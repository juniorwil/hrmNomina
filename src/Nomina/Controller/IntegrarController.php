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
use Principal\Model\IntegrarFunc;      // Integracion de nomina

use Principal\Model\Gnominag; // Procesos generacion de automaticos


class IntegrarController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/integrar/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Integrar nominas"; // Titulo listado
    private $tfor = "Integracion de nominas"; // Titulo formulario
    private $ttab = "Tipo de nomina, Periodo, Tipo de calendario, Grupo, Empleados ,Nomina, Provisiones "; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $f=new IntegrarFunc($this->dbAdapter);
      // Borrar integracion actual y generar una nueva
      $d->modGeneral("delete from n_nomina_e_d_integrar");
      
      $datos = $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup, c.nombre as nomtcale, 
                                        d.nombre as nomtnom,a.estado,a.numEmp,
                                        case when e.id is null then 0 else e.id end as integra 
                                        from n_nomina a 
					inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
					inner join n_tip_nom d on d.id=a.idTnom 
					left join n_nomina_e_d_integrar e on e.idNom = a.id  
                                        where a.estado in (0,1)
                                        group by a.id ");      
      foreach($datos as $dat)
      {
         $id =$dat['id']; 
         $f->getIntegrarNomina($id);
      }      
      $d->modGeneral("update n_nomina_e_d_integrar set codCta = '' where error like '%Sin%'");// Borrar cuentas sin configurar
      
      // Integrar proviciones
      $d->modGeneral("delete from n_provisiones_integrar_p where estado=0");
      
     $datos = $d->getGeneral("select b.idEmp from n_nomina a
                  inner join n_nomina_e b on b.idNom = a.id
                  where a.estado in (0,1)");      
      foreach($datos as $dat)
      {
         $idEmp =$dat['idEmp']; 
         
         // Cesantias
         $idProc = 5;
         $f->getIntProv($idProc, $idEmp, 'Cesantias', 1 );
         // Interes de cesantias
         $idProc = 5;
         $f->getIntProv($idProc, $idEmp, 'Intereses de cesantias', 2 );
         // Interes de cesantias
         $idProc = 6;
         $f->getIntProv($idProc, $idEmp, 'Primas', 3 );         
         // Interes de cesantias
         $idProc = 4;
         $f->getIntProv($idProc, $idEmp, 'Vacaciones',4 );                  
      }            
      $d->modGeneral("delete from n_provisiones_integrar_p where nitTer=''"); // Temmporal porque no deja insertar consltas sin resultados
      // ---
      
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
        "datos"     =>  $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup, c.nombre as nomtcale, 
                                        d.nombre as nomtnom,a.estado,a.numEmp,
                                        case when e.id is null then 0 else e.id end as integra 
                                        from n_nomina a 
					inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
					inner join n_tip_nom d on d.id=a.idTnom 
					left join n_nomina_e_d_integrar e on e.idNom = a.id  
                                        where a.estado in (0,1)
                                        group by a.id "),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin,
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado          
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 
    
    // INTEGRACION CONTABLE -------------------------------------
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
                   $datF = $f->getDiasEmp($idEmp);
                   $campo = 'diasSalud';
                   //$g->getPlanillaE($id, $campo, $datF['valor'] );
                                      
                   // 2. DIAS PENSION
                   $datF = $f->getDiasEmp($idEmp);
                   $campo = 'diasPension';
                   //$g->getPlanillaE($id, $campo, $datF['valor'] );
                   
                   // 3. DIAS RIESGOS
                   $datF = $f->getDiasEmp($idEmp);
                   $campo = 'diasRiesgos';
                   //$g->getPlanillaE($id, $campo, $datF['valor'] );                   

                   
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
                   $valor = $datProv['porc'].' * ibcSalud';
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
                   $valor = $datProv['porc'].' * ibcPension';
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
                   $g->getPlanillaE($id, $campo, $valor );                                      
                   
                   
                   // 12. TARIFA ARL 
                    
                    
                    
                   // 13. FONDOS RIESGOS ARL
                   $datF = $d->getEmp(" and id =".$idEmp );
                   foreach($datF as $dat)
                   {
                       $valor = $dat['idFarp'];
                   }
                   $campo = 'idFonR';
                   $g->getPlanillaE($id, $campo, $valor );                                                                            
                   
                   // 14. APORTES RIESGOS ARL

                   
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
                   $valor = $datProv['porc'].' * ibcCaja';
                   $campo = 'aporCaja';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                                                  
                   
                   
                   // 18. APORTE POR SENA
                   $datProv = $d->getProviciones(' and nombre=9 ');
                   $valor = $datProv['porc'].' * ibcCaja';
                   $campo = 'aporSena';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                                                  
                   
                   // 19. APORTE POR ICBF
                   $datProv = $d->getProviciones(' and nombre=10 ');
                   $valor = $datProv['porc'].' * ibcCaja';
                   $campo = 'aporIcbf';
                   $g->getPlanillaE($id, $campo, $valor );                                                                                                                                     
                   
                } // FIN REGISTRO DE PLANILLA UNICA
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
      
    } // Fin integracion contable
    
}
