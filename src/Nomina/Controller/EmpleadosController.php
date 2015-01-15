<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Nomina\Model\Entity\Empleados; // (C)
use Nomina\Model\Entity\EmpleadosG; // Datos generales
use Nomina\Model\Entity\EmpleadosF; // Cuadro familiar
use Nomina\Model\Entity\EmpleadosE; // Estudios realziados

use Principal\Model\NominaFunc;        
use Principal\Model\Pgenerales; // Parametros generales


class EmpleadosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/empleados/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Empleados activos"; // Titulo listado
    private $tfor = "Actualización de empleado"; // Titulo formulario
    private $ttab = "Cedula, Nombres, Apellidos, Cargo, Pdf, Editar,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $form = new Formulario("form");
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
            "datArb"    =>  $d->getGeneral("select a.id as idSed, a.nombre as nomSed, 
                                            b.nombre as nomCcos, b.id as idCcos 
                                            from t_sedes a 
                                            inner join n_cencostos b on b.idSed=a.id
                                            where b.estado = 0 
                                            order by  b.id"), 
            "ttablas"   =>  $this->ttab,
            "form"      => $form,
            "lin"       =>  $this->lin,
            'url'       => $this->getRequest()->getBaseUrl(),
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado
        );                       

        return new ViewModel($valores);
        
    } // Fin listar registros 

    
    public function listeAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);   
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $d=new AlbumTable($this->dbAdapter);
        // Si es por busqueda
        $con = ' and c.id='.$id;
        $bus = 0;
        if($this->getRequest()->isPost()) // Actulizar datos
        {
           $request = $this->getRequest();
           if ($request->isPost()) {
              $data = $this->request->getPost();        
              $con = " and ( ( a.CedEmp like '%".$data->cedula."%') or ( a.nombre like '%".$data->cedula."%') or ( a.apellido like '%".$data->cedula."%') ) ";
              $bus = 1;
            }
        }

        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $d->getPermisos($this->lin), // Permisos de esta opcion
            "datos"     =>  $d->getEmpM( $con ),            
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,
            "bus"       =>  $bus, // 0 , carga en el ifram 1 es por busqueda
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado
        );                
        $view = new ViewModel($valores);        
        if ( $bus == 0 )
           $this->layout('layout/blancoI'); // Layout del login
        return $view;      
        
    } // Fin listar registros     
 
   // Editar y nuevos datos *********************************************************************************************
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);                       
      // Niveles de aspectos
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $pn = new Pgenerales( $this->dbAdapter );
      $dp = $pn->getGeneral1(1);      

      $d = new AlbumTable($this->dbAdapter);
      // Cargo
      $arreglo='';
      $datos = $d->getCargos(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idCar")->setValueOptions($arreglo);                         
      // Centro de costos
      $arreglo='';
      $datos = $d->getCencos(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idCencos")->setValueOptions($arreglo);                               
      // Grupo de nomina
      $arreglo='';
      $datos = $d->getGrupo2(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idGrupo")->setValueOptions($arreglo);                               
      // Automaticos de nomina
      $arreglo='';
      $datos = $d->getTautoma(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idTau")->setValueOptions($arreglo);                                           
      $form->get("idTau2")->setValueOptions($arreglo);                                           
      $form->get("idTau3")->setValueOptions($arreglo);                                           
      $form->get("idTau4")->setValueOptions($arreglo);                                           
      // Prefijos contables
      $arreglo='';
      $datos = $d->getPrefcont(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idPrej")->setValueOptions($arreglo);                                                                         
      
      // Tipo de contrato
      $arreglo='';
      $datos = $d->getTipcont(); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);                                                                                     
      // Fondos prestacionales ---------------
      // Salud
      $arreglo='';
      $datos = $d->getFondos('1'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idSal")->setValueOptions($arreglo);                                                 
      // Pension
      $arreglo='';
      $datos = $d->getFondos('2'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idPen")->setValueOptions($arreglo);                                                       
      // Arp
      $arreglo='';
      $datos = $d->getFondos('3'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idArp")->setValueOptions($arreglo);                                                             
      // Cesntias
      $arreglo='';
      $datos = $d->getFondos('4'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idCes")->setValueOptions($arreglo);                                                             
      // Caja de compensacion
      $arreglo[1]= 'No aplica';
      $datos = $d->getFondos('5'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idCaja")->setValueOptions($arreglo);                                                                   
      $form->get("idCaja")->setAttribute("value",1);       
      // Fondos aportes voluntarios
      $arreglo[1]= 'No aplica';
      $datos = $d->getFondos('2'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idFav")->setValueOptions($arreglo);                                                                         
      $form->get("idFav")->setAttribute("value",1);       
      // Fondos aportes AFC
      $arreglo[1]= 'No aplica';
      $datos = $d->getFondos('2'); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idFafc")->setValueOptions($arreglo);                                                                               
      $form->get("idFafc")->setAttribute("value",1); 
           
      // Tipo de empleado
      $arreglo='';
      $datos = $d->getTemp(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idTemp")->setValueOptions($arreglo);                                                                                     
      
      // Nivel de estudios
      $arreglo='';
      $datos = $d->getNestudios(""); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idNest")->setValueOptions($arreglo);                                     
      $form->get("idNest2")->setValueOptions($arreglo);                                     
      // Bancos
      $arreglo='';
      $datos = $d->getbancos(""); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }                    
      $form->get("idBanco")->setValueOptions($arreglo);                                     
      // crear libro de vacaciones 
      
      $form->get("estado")->setValueOptions(array("0"=>"Activo","2"=>"Inactivo"));                                           
      
      // Escala salarial
      $arreglo='';
      if ( $dp['escala'] == 1 ) // Escala salarial 0 no, 1 si                 
      {
         $datos = $d->getSalarios(''); 
         foreach ($datos as $dat){
             $idc=$dat['id'];$nom=$dat['salario'];
             $arreglo[$idc]= $nom;
         }              
         $form->get("idSalario")->setValueOptions($arreglo);
      }else{ // no maneja el salario
         $arreglo[1]= "No aplica";
         $form->get("idSalario")->setValueOptions($arreglo)->setAttribute("readOnly", true );
      }      
      
      // Tarifas riesgos arl
      $arreglo='';
      $datos = $d->getTarifas(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'].' - '.$dat['porc'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idTar")->setValueOptions($arreglo);                                           
      
      $foto = 0; 
      $f = new NominaFunc($this->dbAdapter);
      // ------------------------ Fin valores del formulario 
      $datos="";   
      $idTcon = '';   
      $form->get("sueldo")->setAttribute("readOnly", true );       
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Empleados($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                $sueldo = $data->sueldo;                
                // Buscar sueldo dependiendo si maneja escala salrial o no
                $dp = $pn->getGeneral1(1);
                if ( $dp['escala'] == 1 ) // Escala salarial 0 no, 1 si                 
                {   
                   $datSuel = $d->getGeneral1("select * from n_salarios where id=".$data->idSalario);
                   if ($datSuel != '' )
                      $sueldo = $datSuel['salario'];
                }
                // INICIO DE TRANSACCIONES
                $connection = null;
                try 
                {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();                                        
                    $u->actRegistro($data, $sueldo); // Actualizar datos del empleado
                    // Transferir archivos 
                    $File    = $this->params()->fromFiles('image-file');
                    $adapter = new \Zend\File\Transfer\Adapter\Http();
                    $adapter->setValidators(array(new \Zend\Validator\File\Extension
                                        (array('extension'=>array('jpg','jpeg','png')))), $File['name']);                  
                    if (!$adapter->isValid()){
                       echo 'Archivo no valido';
                    }else{                       
                       $datGen = $d->getConfiguraG(" where id=1"); // Obtener datos de configuracion general        
                       $rutaP = $datGen['ruta']; // Ruta padre                    
                       $ruta = $rutaP.'/Datos/Empleados';
                       $adapter->setDestination($ruta);
                       $adapter->addFilter('Rename', 
                            array('target' => ltrim($ruta)."/e".$data->id.".jpg" , 'overwrite' => true));                    

                       if ($adapter->receive($File['name'])) {                       
                          $d->modGeneral("update a_empleados set foto = 1 where id=".$data->id);
                       }                    
                    }                   
                    $connection->commit();                                
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$data->id);
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
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Empleados($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            $foto = $datos['foto'];
            $idTcon = $datos['IdTcon'];
            // Valores guardados
            $form->get("cedula")->setAttribute("value",$datos['CedEmp']); 
            $form->get("nombre")->setAttribute("value",$datos['nombre']); 
            $form->get("apellido1")->setAttribute("value",$datos['apellido']); 
            $form->get("dir")->setAttribute("value",$datos['DirEmp']); 
            $form->get("numero")->setAttribute("value",$datos['TelEmp']); 
            $form->get("sexo")->setAttribute("value",$datos['SexEmp']); 
            $form->get("fecDoc")->setAttribute("value",$datos['FecNac']); 
            $form->get("email")->setAttribute("value",strtoupper($datos['email']));             
            $form->get("estado")->setAttribute("value",$datos['estado'] );             
            $form->get("idTar")->setAttribute("value",$datos['idRies'] );             
            
            // Fondos
            $form->get("idSal")->setAttribute("value",$datos['idFsal']); 
            $form->get("idPen")->setAttribute("value",$datos['idFpen']); 
            $form->get("idCes")->setAttribute("value",$datos['idFces']);              
            $form->get("idArp")->setAttribute("value",$datos['idFarp']); 
            $form->get("idCaja")->setAttribute("value",$datos['idCaja']); 
            $form->get("idFav")->setAttribute("value",$datos['idFav']);              
            $form->get("idFafc")->setAttribute("value",$datos['idFafc']);              
            
            // Contractuales            
            $form->get("tipo")->setAttribute("value",$datos['IdTcon']);                              
            $form->get("fecIng")->setAttribute("value",$datos['fecIng']);                              
            $form->get("fecPvac")->setAttribute("value",$datos['fecUlVac']);                              
            $form->get("idTemp")->setAttribute("value",$datos['idTemp']);                              
            
            // Clasificaciones
            //$form->get("sueldo")->setAttribute("value",number_format($datos['sueldo'], 2) ); 
            $form->get("sueldo")->setAttribute("value", $datos['sueldo'] ); 
            $form->get("idCar")->setAttribute("value",$datos['idCar']); 
            $form->get("idCencos")->setAttribute("value",$datos['idCcos']); 
            $form->get("idGrupo")->setAttribute("value",$datos['idGrup']); 
            $form->get("idTau")->setAttribute("value",$datos['idTau']);                              
            $form->get("idTau2")->setAttribute("value",$datos['idTau2']);                              
            $form->get("idTau3")->setAttribute("value",$datos['idTau3']);                              
            $form->get("idTau4")->setAttribute("value",$datos['idTau4']);                              
            $form->get("idPrej")->setAttribute("value",$datos['idPref']);                                          
            $form->get("formaPago")->setAttribute("value",$datos['formaPago']);                              
            $form->get("idBanco")->setAttribute("value",$datos['idBanco']);                              
            $form->get("numCuenta")->setAttribute("value",$datos['numCuenta']);                                                      
            $form->get("idSalario")->setAttribute("value",$datos['idSal']);                                                      
            
            // Datos generales -----------------------
            ///// -------------------------------------
            // Aspectos fisicos
            $form->get("estatura")->setAttribute("value",$datos['estatura'] );             
            $form->get("sangre")->setAttribute("value",$datos['sangre'] );             
            $form->get("alergias")->setAttribute("value",$datos['alergias'] );             
            $form->get("operaciones")->setAttribute("value",$datos['operaciones'] );  
            $form->get("enfermedades")->setAttribute("value",$datos['enfermedades'] );              
            $form->get("limitacion")->setAttribute("value",$datos['limitacion'] );                          
            $form->get("fuma")->setAttribute("value",$datos['fuma'] );             
            $form->get("bebe")->setAttribute("value",$datos['bebe'] );  
            $form->get("lentes")->setAttribute("value",$datos['lentes'] );  
            // Aficiones y gustos
            $form->get("clubSocial")->setAttribute("value",$datos['clubSocial'] );             
            $form->get("deportes")->setAttribute("value",$datos['deportes'] );  
            $form->get("libros")->setAttribute("value",$datos['libros'] );  
            $form->get("musica")->setAttribute("value",$datos['musica'] );  
            $form->get("otrasAct")->setAttribute("value",$datos['otrasAct'] );  
            
            $datos = $datos['nombre'].' '.$datos['apellido'];             
            
         }        
      }
      $valores=array
      (
          "titulo"  => $this->tfor,
          "form"    => $form,
          "datFam"  => $d->getGeneral("select *,(YEAR(CURRENT_DATE) - YEAR(fechaNac)) - (RIGHT(CURRENT_DATE,5) < RIGHT(fechaNac,5)) AS edad  
                                       from a_empleados_f where idEmp=".$id." "
                  . "                  order by (YEAR(CURRENT_DATE) - YEAR(fechaNac)) - (RIGHT(CURRENT_DATE,5) < RIGHT(fechaNac,5))"),// Cuadro familiar
          'datEst'  => $d->getGeneral("select b.*, c.nombre as nomNest
                                       from a_empleados_e b 
                                       inner join t_nivel_estudios c on c.id=b.idNest 
                                       where b.idEmp=".$id),           
          'datCon'  => $d->getGeneral("select * from n_emp_contratos where idEmp=".$id),// Contratos 
          'url'     => $this->getRequest()->getBaseUrl(),
          'id'      => $id,
          'idTcon'  => $idTcon,
          'datos'   => $datos,  
          'foto'    => $foto,  // 0 no maneja foto , 1 si maneja foto
          "lin"     => $this->lin
      );                
      return new ViewModel($valores);      
   } // Fin actualizar datos 
   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Empleados($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }

   // Guardar cuadro familiar
   public function listgfAction() 
   { 
      $id = (int) $this->params()->fromRoute('id', 0);
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();
            //print_r($data);
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u    = new EmpleadosF($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            //print_r($data);    
            $u->actRegistro($data);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin."a/".$data->id);
        }   
      }      
      
   } // Fin guardar cuadro familiar
   // Borrar cuadro familiar
   public function listfdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new EmpleadosF($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                     
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select idEmp from a_empleados_f where id=".$id);
            $u->delRegistro($id);            
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin."a/".$datos['idEmp']);
          }
          
   }      
   
   // Nivel de estudios
   public function listgeAction() 
   { 
      $id = (int) $this->params()->fromRoute('id', 0);
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u    = new EmpleadosE($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            //print_r($data);    
            $u->actRegistro($data);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin."a/".$data->id);
        }   
      }      
      
   } // Fin nivel de estudios
      
   // Borrar nivel de estudios
   public function listedAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new EmpleadosE($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                     
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select idEmp from a_empleados_e where id=".$id);
            $u->delRegistro($id);            
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin."a/".$datos['idEmp']);
          }
          
   }   

   // Renovacion de contrato
   public function listrcAction() 
   { 
      $id = (int) $this->params()->fromRoute('id', 0);
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d = new AlbumTable($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            //print_r($data);    
            $d->getGeneral1("delete from n_emp_contratos where idEmp = ".$data->id );
            $d->modGeneral("insert into n_emp_contratos ( idEmp, fechaI, fechaF ) values(".$data->id.",'".$data->fechaIniCon."','".$data->fechaFinCon."' )" );

            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin."a/".$data->id);
        }   
      }      
      
   } // Fin renovacion de contrato

}
